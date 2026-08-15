<?php

namespace App\Console\Commands;

use App\Models\Seo;
use App\Models\SeoScoreHistory;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Console\Command;

class SeedSeoScoreHistory extends Command
{
    protected $signature = 'seo:seed-score-history
    {--force : Delete existing score history before seeding}
    {--full : Use all products and categories instead of only entities with SEO records}';
    protected $description = 'Seed 30 days of fake SEO score history with a realistic upward trend for the score chart';

    public function handle(): int
    {
        if ($this->option('force')) {
            $this->warn('🗑️  Clearing existing SEO score history...');
            SeoScoreHistory::truncate();
        }

        $existingCount = SeoScoreHistory::count();
        if ($existingCount > 0) {
            $this->error("Score history already has {$existingCount} records. Use --force to re-seed.");
            return self::FAILURE;
        }

        $this->info('🌱 Seeding SEO score history for 30 days...');
        $start = microtime(true);

        // Collect entities — use all products+categories with --full, otherwise only entities with SEO records
        $entities = [];
        if ($this->option('full')) {
            $this->warn('Using all products and categories (--full flag)...');
            foreach (Product::all() as $p) {
                $entities[] = ['entity_type' => 'product', 'entity_id' => $p->id];
            }
            foreach (Category::all() as $c) {
                $entities[] = ['entity_type' => 'category', 'entity_id' => $c->id];
            }
        } else {
            $seos = Seo::all();
            if ($seos->isEmpty()) {
                $this->warn('No SEO records found. Creating placeholder history using products and categories...');
                foreach (Product::all() as $p) {
                    $entities[] = ['entity_type' => 'product', 'entity_id' => $p->id];
                }
                foreach (Category::all() as $c) {
                    $entities[] = ['entity_type' => 'category', 'entity_id' => $c->id];
                }
            } else {
                $entities = $seos->map(fn($s) => ['entity_type' => $s->entity_type, 'entity_id' => $s->entity_id])->toArray();
            }
        }

        if (empty($entities)) {
            $this->error('No products, categories, or SEO records found. Nothing to seed.');
            return self::FAILURE;
        }

        $totalInserted = 0;
        $today = now()->endOfDay();

        // Generate a realistic upward trend with daily fluctuations
        // Start low (~30-40) and gradually climb to ~60-80
        foreach (range(30, 1) as $daysAgo) {
            $date = (clone $today)->subDays($daysAgo);

            // Base score escalates over time: starts ~35, ends ~70
            $progress = (30 - $daysAgo) / 29; // 0 to 1
            $baseScore = (int) round(35 + ($progress * 40));

            // Add daily variance: ±10 points for organic noise
            $dayFactor = mt_rand(-100, 100) / 100; // -1 to 1
            $dailyVariance = (int) round($dayFactor * 10);

            // Every 7 days, simulate a minor improvement (audit/optimization)
            $optimizationBoost = ($daysAgo % 7 === 0) ? mt_rand(3, 8) : 0;

            // Score for this day
            $dayScore = max(10, min(100, $baseScore + $dailyVariance + $optimizationBoost));

            $recordsForDay = 0;

            foreach ($entities as $entity) {
                // Add per-entity variation around the daily average (±15 pts)
                $entityVariance = mt_rand(-150, 150) / 100 * 10;
                $entityScore = max(5, min(100, $dayScore + (int) round($entityVariance)));

                // Build a realistic breakdown that matches the audit structure
                $breakdown = [
                    'meta_title' => ['score' => $entityScore >= 70 ? 15 : ($entityScore >= 40 ? 10 : 5), 'max' => 15, 'status' => $entityScore >= 70 ? 'good' : ($entityScore >= 40 ? 'needs_work' : 'poor'), 'message' => ''],
                    'meta_description' => ['score' => $entityScore >= 70 ? 15 : ($entityScore >= 40 ? 10 : 5), 'max' => 15, 'status' => $entityScore >= 70 ? 'good' : ($entityScore >= 40 ? 'needs_work' : 'poor'), 'message' => ''],
                    'open_graph' => ['score' => $entityScore >= 60 ? 10 : ($entityScore >= 40 ? 6 : 3), 'max' => 10, 'status' => $entityScore >= 60 ? 'good' : ($entityScore >= 40 ? 'incomplete' : 'missing'), 'message' => ''],
                    'twitter_cards' => ['score' => $entityScore >= 60 ? 10 : ($entityScore >= 40 ? 6 : 3), 'max' => 10, 'status' => $entityScore >= 60 ? 'good' : ($entityScore >= 40 ? 'incomplete' : 'missing'), 'message' => ''],
                    'canonical_url' => ['score' => $entityScore >= 50 ? 10 : 0, 'max' => 10, 'status' => $entityScore >= 50 ? 'good' : 'missing', 'message' => ''],
                    'structured_data' => ['score' => $entityScore >= 60 ? 15 : ($entityScore >= 40 ? 10 : 5), 'max' => 15, 'status' => $entityScore >= 60 ? 'good' : ($entityScore >= 40 ? 'incomplete' : 'missing'), 'message' => ''],
                    'meta_keywords' => ['score' => $entityScore >= 50 ? 5 : 3, 'max' => 5, 'status' => $entityScore >= 50 ? 'good' : 'suboptimal', 'message' => ''],
                    'robots_meta' => ['score' => $entityScore >= 30 ? 5 : 3, 'max' => 5, 'status' => 'good', 'message' => ''],
                    'sitemap_priority' => ['score' => $entityScore >= 30 ? 5 : 2, 'max' => 5, 'status' => 'good', 'message' => ''],
                    'hreflang' => ['score' => $entityScore >= 50 ? 5 : 2, 'max' => 5, 'status' => $entityScore >= 50 ? 'good' : 'not_set', 'message' => ''],
                    'content_language' => ['score' => $entityScore >= 50 ? 5 : 2, 'max' => 5, 'status' => $entityScore >= 50 ? 'good' : 'not_set', 'message' => ''],
                ];

                SeoScoreHistory::create([
                    'entity_type' => $entity['entity_type'],
                    'entity_id' => $entity['entity_id'],
                    'score' => $entityScore,
                    'breakdown' => $breakdown,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                $recordsForDay++;
                $totalInserted++;
            }

            $dayLabel = $date->format('M d');
            $this->line("   {$dayLabel}: avg score {$dayScore} across {$recordsForDay} entities");
        }

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("   ✓ {$totalInserted} score history records seeded in {$elapsed}s");
        $this->info('📊 Visit the SEO Dashboard to see the trend chart in action!');

        return self::SUCCESS;
    }
}
