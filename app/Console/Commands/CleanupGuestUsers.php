<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupGuestUsers extends Command
{
    protected $signature = 'guest-users:cleanup {--days=30 : Delete/anonymize guest users older than this many days} {--dry-run : Log what would be done without actually deleting}';
    protected $description = 'Clean up guest users created during checkout with placeholder emails (guest_xxx@checkout.local)';

    public function handle(): int
    {
        $retentionDays = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($retentionDays);

        $this->info("Looking for guest users older than {$retentionDays} days (before {$cutoff->toDateString()})...");
        if ($dryRun) {
            $this->warn('── DRY RUN — no changes will be made ──');
        }

        // Find guest users with placeholder emails (guest_xxx@checkout.local), created before cutoff
        // Excludes already-anonymized users (deleted_{id}@checkout.local)
        $guests = User::where('email', 'like', 'guest_%@checkout.local')
            ->where('created_at', '<', $cutoff)
            ->withCount('orders')
            ->get();

        if ($guests->isEmpty()) {
            $this->info('No guest users found matching the criteria.');
            return Command::SUCCESS;
        }

        $this->info("Found {$guests->count()} guest user(s) to process.");

        $deleted = 0;
        $anonymized = 0;

        foreach ($guests as $guest) {
            $orderCount = $guest->orders_count;

            if ($orderCount === 0) {
                // No orders — safe to delete entirely
                if ($dryRun) {
                    $this->line("  [DELETE] Guest {$guest->id} ({$guest->email}) — no orders");
                } else {
                    $guest->delete();
                    $this->line("  [DELETED] Guest {$guest->id} ({$guest->email})");
                }
                $deleted++;
            } else {
                // Has orders — anonymize personal data, mark inactive
                if ($dryRun) {
                    $this->line("  [ANONYMIZE] Guest {$guest->id} ({$guest->email}) — {$orderCount} order(s)");
                } else {
                    $guest->update([
                        'first_name' => 'Deleted',
                        'last_name' => 'Guest',
                        'email' => 'deleted_' . $guest->id . '@checkout.local',
                        'phone_number' => null,
                        'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                        'is_active' => false,
                        'is_blocked' => true,
                    ]);
                    $this->line("  [ANONYMIZED] Guest {$guest->id} — {$orderCount} order(s) preserved");
                }
                $anonymized++;
            }
        }

        $this->info("Done: {$deleted} deleted, {$anonymized} anonymized.");
        Log::info("[CleanupGuestUsers] Completed — deleted: {$deleted}, anonymized: {$anonymized}");

        return Command::SUCCESS;
    }
}
