@echo off
REM ═══════════════════════════════════════════════════════════════
REM  Local Queue Worker — Windows Development
REM ═══════════════════════════════════════════════════════════════
REM
REM  Starts the Laravel queue worker in the foreground.
REM  Keep this terminal window open while you're developing.
REM  Press Ctrl+C to stop the worker.
REM
REM  Queue Driver: database (from .env: QUEUE_CONNECTION=database)
REM
REM  Usage:  Double-click this file OR run from terminal:
REM          deploy\supervisor\local-queue-worker.bat
REM ═══════════════════════════════════════════════════════════════

echo.
echo ╔═══════════════════════════════════════════════╗
echo ║   LARAVEL QUEUE WORKER — LOCAL DEVELOPMENT    ║
echo ╚═══════════════════════════════════════════════╝
echo.
echo Queue Driver: database
echo Workers:      1
echo Autorestart:  yes (restarts every hour)
echo.
echo Press Ctrl+C to stop the worker.
echo.

cd /d "%~dp0..\.."

REM Check if PHP is available
where php >nul 2>nul || (
    echo.
    echo [ERROR] PHP not found in your system PATH.
    echo Make sure PHP is installed and added to your PATH environment variable.
    echo.
    pause
    exit /b 1
)

php artisan queue:work database --sleep=3 --tries=3 --max-time=3600

echo.
echo Worker stopped.
pause
