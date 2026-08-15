<?php

namespace App\Console\Commands;

use App\Services\AbandonedCartService;
use App\Models\AbandonedCart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAbandonedCarts extends Command
{
    protected $signature = 'abandoned-carts:process
                            {--hours=2 : Minimum hours since cart was created to send reminder}
                            {--dry-run : Preview which carts would receive reminders without sending}';

    protected $description = 'Send automated reminders for abandoned carts that have not been reminded yet';

    public function __construct(
        protected AbandonedCartService $abandonedCartService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours);

        $this->info("Processing abandoned carts older than {$hours} hour(s)...");

        $carts = AbandonedCart::with('user')
            ->where('reminder_sent', false)
            ->where('recovered', false)
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($carts->isEmpty()) {
            $this->info('No abandoned carts to process.');
            return Command::SUCCESS;
        }

        $this->info("Found {$carts->count()} abandoned cart(s) eligible for reminders.");

        $sent = 0;
        $errors = 0;

        foreach ($carts as $cart) {
            if ($dryRun) {
                $userEmail = $cart->user?->email ?? 'guest';
                $this->line("  [DRY-RUN] Would remind cart {$cart->id} (user: {$userEmail})");
                $sent++;
                continue;
            }

            try {
                $this->abandonedCartService->sendReminder($cart->id);
                $sent++;
                $this->line("  ✓ Reminded cart {$cart->id}");
            } catch (\Exception $e) {
                $errors++;
                Log::error("[AbandonedCart] Failed to process cart {$cart->id}: {$e->getMessage()}");
                $this->error("  ✗ Failed cart {$cart->id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Done. Sent: {$sent}, Errors: {$errors}");

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
