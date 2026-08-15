# Deployment Guide

> **Applies to:** LUXE E-commerce (Laravel + React + Socket.IO)
> **Last Updated:** July 2026

---

## Prerequisites

| Dependency | Version | Purpose |
|------------|---------|---------|
| PHP | 8.0+ | Laravel backend |
| Composer | 2.x | PHP package management |
| Node.js | 18+ | Frontend build & Socket.IO server |
| npm | 9+ | JS package management |
| MySQL / MariaDB | 8.0+ / 10.3+ | Database |
| Web Server | Apache / Nginx | Serving the app |

Required PHP extensions: `pdo`, `mbstring`, `xml`, `curl`, `gd`, `bcmath`, `json`, `openssl`, `tokenizer`

---

## Quick Start

Run the automated deployment script:

```bash
chmod +x deploy.sh
./deploy.sh
```

The script handles everything below automatically. For manual deployment, follow the steps below.

---

## Manual Deployment

### 1. Environment Configuration

```bash
cd luxe-ecommerce-laravel

# Create .env from example if not exists
cp .env.example .env

# Generate application key
php artisan key:generate --force
```

Edit `.env` with your production settings:

| Variable | Recommended Value | Notes |
|----------|-------------------|-------|
| `APP_ENV` | `production` | Disables debug mode |
| `APP_DEBUG` | `false` | Never enable in production |
| `APP_URL` | `https://yourdomain.com` | Must match your domain |
| `DB_*` | (your database credentials) | MySQL/MariaDB |
| `QUEUE_CONNECTION` | `database` | Database queue (no Redis needed) |
| `CACHE_DRIVER` | `file` | File-based caching |
| `SESSION_DRIVER` | `file` | File-based sessions |
| `BROADCAST_DRIVER` | `null` | SocketService handles real-time directly |
| `SOCKET_SERVER_URL` | `https://your-socket-server.com` | Node.js Socket.IO server URL |
| `INTERNAL_SOCKET_KEY` | (random 48-char secret) | Shared secret between Laravel & Socket server |
| `MAIL_*` | (your SMTP settings) | For transactional emails |
| `SANCTUM_STATEFUL_DOMAINS` | `yourdomain.com` | SPA authentication |
| `SESSION_DOMAIN` | `.yourdomain.com` | Shared session across subdomains |

### 2. Install PHP Dependencies

```bash
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
```

### 3. Database Setup

```bash
# Create queue tables (if not already present)
php artisan queue:table
php artisan queue:failed-table

# Run all migrations
php artisan migrate --force
```

### 4. Storage Link

```bash
php artisan storage:link
```

### 5. Cache Optimizations

```bash
# Clear any old cache
php artisan optimize:clear

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache    # if events are defined
```

### 6. Build React Frontend

```bash
cd ../luxe-ecommerce-laravel-frontend

# Install dependencies (if not already)
npm ci

# Build for production
VITE_STORE_NAME="Your Store Name" npm run build

# Copy assets to Laravel public/
cp -r dist/* ../luxe-ecommerce-laravel/public/
rm -rf dist
cd ../luxe-ecommerce-laravel
```

> **Note:** The frontend build outputs to `dist/`. The deploy script copies `index.html`, assets, and PWA manifest to Laravel's `public/` directory, which serves them directly.

### 7. Set Up Socket.IO Server

```bash
cd socket-server

# Install dependencies
npm install

# Configure environment
cp .env.example .env
```

Edit `socket-server/.env`:

| Variable | Purpose |
|----------|---------|
| `PORT` | Server port (default: 3001) |
| `CORS_ORIGINS` | Comma-separated allowed origins (e.g. `https://yourdomain.com,https://admin.yourdomain.com`) |
| `JWT_SECRET` | Must match Laravel's `APP_KEY` for JWT verification |
| `INTERNAL_SOCKET_KEY` | Must match `INTERNAL_SOCKET_KEY` in Laravel's `.env` |

The socket server synchronizes `JWT_SECRET` from Laravel's `APP_KEY` and `INTERNAL_SOCKET_KEY` automatically when run via `deploy.sh`.

Start the socket server:

```bash
npm start
```

For production, use a process manager:

```bash
# Install PM2 globally
npm install -g pm2

# Start with PM2
pm2 start server.js --name "luxe-socket-server"

# Save PM2 process list
pm2 save
pm2 startup   # restart on server boot
```

### 8. File Permissions

```bash
chmod -R 775 storage bootstrap/cache
chmod -R 775 public/assets

mkdir -p storage/framework/{sessions,views,cache,testing}
mkdir -p storage/logs
mkdir -p bootstrap/cache
```

### 9. Web Server Configuration

#### Nginx

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/luxe-ecommerce-laravel/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php index.html;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Apache

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /path/to/luxe-ecommerce-laravel/public

    <Directory /path/to/luxe-ecommerce-laravel/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Ensure `mod_rewrite` is enabled: `sudo a2enmod rewrite`

---

## Queue Workers

This project uses **Laravel Queues** for async processing of emails, SMS, and notifications. A **queue worker** is a background process that picks up jobs from the `jobs` table and executes them.

Four queue jobs are defined:

| Job | Purpose |
|-----|---------|
| `SendEmailJob` | Sends transactional emails |
| `SendSmsJob` | Sends SMS via Twilio |
| `SendNotificationJob` | Creates in-app notifications |
| `GenerateBarcodeLabelsJob` | Generates barcode label PDFs in the background |

Without a running queue worker, dispatched jobs will **sit in the `jobs` table forever** and never be executed.

### Environment Check

```bash
QUEUE_CONNECTION=database    # shared hosting (recommended)
— or —
QUEUE_CONNECTION=redis       # VPS (requires Redis server)

QUEUE_FAILED_DRIVER=database-uuids
```

### Required Tables

```bash
php artisan queue:table        # creates jobs table migration
php artisan queue:failed-table # creates failed_jobs migration
php artisan migrate
```

### Option 1: Shared Hosting — Cron Job

> **Use when:** You have cPanel / shared hosting without SSH access or the ability to run persistent processes.

Since shared hosting doesn't allow long-running daemons, we use a **cron job** that runs every minute, processes all pending jobs, and then shuts down.

#### Step 1: Find your PHP binary path

Most shared hosting providers put PHP at one of these paths:

```bash
/usr/local/bin/php
/usr/bin/php
/usr/local/php81/bin/php   # version-specific
/opt/cpanel/ea-php81/root/usr/bin/php  # cPanel EasyApache
```

**Ask your hosting provider** for the exact path, or check via cPanel's "PHP Selector" section.

#### Step 2: Find your project absolute path

The full path to your Laravel project on the server. For example:

```
/home/yourusername/public_html     # typical cPanel
/home/yourusername/domains/domain.com/public_html
```

#### Step 3: Add the cron jobs

In **cPanel → Cron Jobs**, add these two cron jobs:

**Laravel Scheduler** (runs scheduled tasks like backups, analytics, cleanup):
```
* * * * * cd /home/yourusername/public_html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

**Queue Worker** (processes queued jobs every minute):
```
* * * * * cd /home/yourusername/public_html && /usr/local/bin/php artisan queue:work database --stop-when-empty --sleep=3 --tries=3 >> /dev/null 2>&1
```

#### What each flag does:

| Flag | Purpose |
|------|---------|
| `database` | Queue driver (must match `.env`) |
| `--stop-when-empty` | **Critical** — worker exits after all jobs are processed, preventing multiple instances from stacking up |
| `--sleep=3` | Wait 3 seconds between polling for new jobs (reduces DB load) |
| `--tries=3` | Retry failed jobs up to 3 times before moving to `failed_jobs` |
| `>> /dev/null 2>&1` | Discard output (prevents cron from emailing you every minute) |

> **⚠️ Important:** If you omit `--stop-when-empty`, a new cron process will start every minute, and **multiple workers will pile up** — exhausting server memory and hitting database connection limits.

#### Monitor failed jobs (Admin Panel)

```
GET  /api/v1/admin/queue/failed-jobs       → list failed jobs (paginated)
POST /api/v1/admin/queue/retry/{uuid}      → retry a specific failed job
POST /api/v1/admin/queue/retry-all         → retry ALL failed jobs
DELETE /api/v1/admin/queue/flush-failed    → clear all failed jobs
```

Alternatively, SSH in and run:

```bash
php artisan queue:failed      # list
php artisan queue:retry all   # retry all
php artisan queue:flush       # clear all
```

#### Verify the cron job works

Dispatch a test job and wait a minute:

```bash
php artisan tinker --execute="dispatch(new App\Jobs\SendEmailJob('test@example.com', 'Test', '<p>Hello</p>'))"
```

After ≤60 seconds, check:

```bash
php artisan queue:failed   # should be empty if successful
```

### Option 2: VPS — Supervisor (Recommended for Production)

> **Use when:** You have a VPS / dedicated server with root/sudo access.

**Supervisor** is a process manager that keeps your queue worker running 24/7 and restarts it automatically if it crashes.

#### Step 1: Install Supervisor

```bash
sudo apt update
sudo apt install supervisor -y
```

#### Step 2: Create worker configuration

A ready-to-use config file is included in the project at:

```
deploy/supervisor/laravel-worker.conf
```

Copy it to Supervisor's config directory:

```bash
sudo cp deploy/supervisor/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf
```

Then edit the file to update the project path:

```bash
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

> ⚠️ **Important:** Replace `/var/www/html` with your actual project path.

The config file looks like this:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

**Key configuration values:**

| Setting | Value | Why |
|---------|-------|-----|
| `command` | `queue:work database --sleep=3 --tries=3 --max-time=3600` | Process database queue; restart worker every hour to prevent memory leaks |
| `numprocs` | `4` | Run 4 parallel workers for higher throughput |
| `user` | `www-data` | Match your web server user for correct file permissions |
| `autostart` | `true` | Start worker when server boots |
| `autorestart` | `true` | Restart worker if it crashes |
| `stopwaitsecs` | `3600` | Don't kill a running job; wait up to 1 hour |
| `--max-time=3600` | **Critical** | Restart the PHP process every hour to prevent memory leaks |

> **Adjust paths:** Replace `/var/www/html` with your actual project path.

#### Step 3: Start Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

#### Step 4: Check worker status

```bash
sudo supervisorctl status

# Expected output:
# laravel-worker:laravel-worker_00   RUNNING   pid 12345, uptime 2:15:30
# laravel-worker:laravel-worker_01   RUNNING   pid 12346, uptime 2:15:30
# laravel-worker:laravel-worker_02   RUNNING   pid 12347, uptime 2:15:30
# laravel-worker:laravel-worker_03   RUNNING   pid 12348, uptime 2:15:30
```

#### Useful Supervisor commands

```bash
sudo supervisorctl status                  # check all workers
sudo supervisorctl restart laravel-worker:* # restart all workers
sudo supervisorctl stop laravel-worker:*    # stop all workers
sudo supervisorctl tail laravel-worker:00   # view worker logs
```

#### Enable Redis (Optional)

If you switch to Redis for better performance:

1. Install Redis: `sudo apt install redis-server`
2. Set `QUEUE_CONNECTION=redis` in `.env`
3. Update Supervisor command:

```ini
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

4. Consider **Laravel Horizon** for a beautiful dashboard:

```bash
composer require laravel/horizon
php artisan horizon:install
```

Then replace the worker command with:

```ini
command=php /var/www/html/artisan horizon
```

### Option 3: Alternative Queue Drivers

| Driver | Setup | Best For |
|--------|-------|----------|
| **database** | ✅ Zero setup | Shared hosting |
| **redis** | ❌ Requires Redis server | VPS, high throughput |
| **sqs** | ❌ Requires AWS account | Serverless, auto-scaling |

---

## Cron Jobs Summary

The deploy script sets up two cron jobs that run every minute:

### 1. Laravel Scheduler
Runs scheduled tasks defined in `app/Console/Kernel.php`:

| Task | Frequency |
|------|-----------|
| `backup:run` | As scheduled |
| `ads:process-scheduled` | Hourly |
| `campaigns:process-scheduled` | Every 5 minutes |
| `maintenance:check-schedule` | Every minute |
| `analytics:aggregate-daily --days=1` | Daily at 23:55 |
| `guest-users:cleanup --days=30` | Daily at 03:00 |
| `exports:cleanup --days=30` | Daily at 03:30 |

### 2. Queue Worker
Processes queued jobs every minute using `--stop-when-empty` (exits after draining the queue).

### Adding to crontab

```bash
(crontab -l 2>/dev/null
  echo "# ── LUXE Scheduler ──"
  echo "* * * * * cd /path/to/luxe-ecommerce-laravel && php artisan schedule:run >> /dev/null 2>&1"
  echo "# ── LUXE Queue Worker ──"
  echo "* * * * * cd /path/to/luxe-ecommerce-laravel && php artisan queue:work database --stop-when-empty --sleep=3 --tries=3 --max-time=3600 >> /dev/null 2>&1"
) | crontab -
```

---

## Important Config Values

### `config/queue.php` — Key settings

```php
'database' => [
    'driver' => 'database',
    'table' => 'jobs',
    'queue' => 'default',
    'retry_after' => 90,    // ⚠️ Must be greater than any job's max execution time
    'after_commit' => false, // Set true for better data consistency
],
```

### Timeout Rules

```
retry_after (90s) > timeout (60s) > job execution time
```

- `retry_after` in `config/queue.php` — how long before Laravel releases a job back to the queue
- `--timeout` passed to `queue:work` — PHP max execution for the worker
- **`retry_after` must always be > `--timeout`**, or a job will be retried before it finishes

---

## Troubleshooting

### "No jobs processed" / Jobs stuck in queue

1. **Verify worker is running:**
   ```bash
   # Shared hosting — check cron job is enabled in cPanel
   # VPS — run:
   sudo supervisorctl status
   ```

2. **Check the `jobs` table:**
   ```sql
   SELECT COUNT(*) FROM jobs;
   SELECT * FROM jobs ORDER BY id DESC LIMIT 5;
   ```

3. **Manually process one job:**
   ```bash
   php artisan queue:work database --once --verbose
   ```

4. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep "SendEmailJob\|SendSmsJob\|SendNotificationJob"
   ```

### "Class not found" errors

```bash
composer dump-autoload
php artisan optimize
```

### "Too many open files" / Memory spikes

- Reduce `numprocs` in Supervisor config
- Add `--max-time=1800` to restart workers more frequently
- Switch from `database` to `redis` driver for better performance

### Frontend not updating after deploy

1. Clear browser cache (hard refresh: Ctrl+Shift+R / Cmd+Shift+R)
2. Verify `public/index.html` was updated (check the Vite asset hash in the file)
3. If using a CDN, purge the cache
4. Run `php artisan view:cache` after copying frontend assets

### Socket.IO connection failing

1. Verify `SOCKET_SERVER_URL` is correct in Laravel's `.env`
2. Check the socket server is running: `pm2 status` or `ps aux | grep server.js`
3. Verify CORS origins in `socket-server/.env` include your domain
4. Check firewall rules allow the socket server port

---

## Quick Reference

### Shared Hosting (cron)

| Task | Command |
|------|---------|
| Edit cron | cPanel → Cron Jobs |
| Scheduler cron | `* * * * * cd /home/user/public_html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1` |
| Queue worker cron | `* * * * * cd /home/user/public_html && /usr/local/bin/php artisan queue:work database --stop-when-empty --sleep=3 --tries=3 >> /dev/null 2>&1` |
| Check failed jobs | `php artisan queue:failed` |
| Retry all failed | `php artisan queue:retry all` |

### VPS (Supervisor)

| Task | Command |
|------|---------|
| Install | `sudo apt install supervisor` |
| Start workers | `sudo supervisorctl start laravel-worker:*` |
| Stop workers | `sudo supervisorctl stop laravel-worker:*` |
| Restart workers | `sudo supervisorctl restart laravel-worker:*` |
| Check status | `sudo supervisorctl status` |
| View logs | `sudo supervisorctl tail laravel-worker:00` |
| Config file | `/etc/supervisor/conf.d/laravel-worker.conf` or `deploy/supervisor/laravel-worker.conf` |

### Frontend Build

| Task | Command |
|------|---------|
| Install deps | `cd luxe-ecommerce-laravel-frontend && npm ci` |
| Build | `cd luxe-ecommerce-laravel-frontend && npm run build` |
| Copy to Laravel | `cp -r luxe-ecommerce-laravel-frontend/dist/* luxe-ecommerce-laravel/public/` |
| Finalize | `rm -rf luxe-ecommerce-laravel-frontend/dist` |
| Use deploy script | `cd luxe-ecommerce-laravel && ./deploy.sh` |

### Socket Server

| Task | Command |
|------|---------|
| Start (dev) | `cd socket-server && npm start` |
| Start (prod) | `cd socket-server && pm2 start server.js --name "luxe-socket-server"` |
| Restart | `pm2 restart luxe-socket-server` |
| View logs | `pm2 logs luxe-socket-server` |
| Auto-start on boot | `pm2 startup && pm2 save` |
