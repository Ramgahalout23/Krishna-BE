<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite indexes targeting the slow homepage, app-init, and listing queries.
     *
     * These indexes are designed to cover the most expensive queries identified
     * during the performance audit:
     *
     * HomepageController:
     *   - Banner::where('is_active',true)->where('type','HERO')->orderBy('position')
     *     → covers WHERE filter + ORDER BY with a single index scan
     *   - Product::where('status','PUBLISHED')->where('is_featured',true)->latest()->take(8)
     *     → extends existing (status, is_featured) to include created_at for covering order
     *   - Product::where('status','PUBLISHED')->orderBy('view_count','desc')->take(8)
     *     → covers WHERE + ORDER BY (best sellers)
     *   - Review::where('is_moderated',true)->where('is_flagged',false)->latest()->take(20)
     *     → composite covers the WHERE filter + ORDER BY (homepage testimonials)
     *   - Reel::where('is_active',true)->orderBy('display_order')
     *     → composite covering WHERE + ORDER BY
     *   - CuratedLook::where('is_active',true)->orderBy('display_order')
     *     → composite covering WHERE + ORDER BY
     *   - Promotion::where('is_active',true)->orderBy('created_at','desc')
     *     → covers active filter + date ordering
     */
    public function up(): void
    {
        // ── Banners: WHERE is_active=1 AND type='HERO' ORDER BY position ──
        $this->addCompositeIndex('banners', ['is_active', 'type', 'position'], 'idx_banners_active_type_position');

        // ── Products: WHERE status='PUBLISHED' AND is_featured=1 ORDER BY created_at DESC ──
        $this->addCompositeIndex('products', ['status', 'is_featured', 'created_at'], 'idx_products_published_featured_date');

        // ── Products: WHERE status='PUBLISHED' ORDER BY view_count DESC ──
        $this->addCompositeIndex('products', ['status', 'view_count'], 'idx_products_published_views');

        // ── Reviews: WHERE is_moderated=1 AND is_flagged=0 ORDER BY created_at DESC ──
        $this->addCompositeIndex('reviews', ['is_moderated', 'is_flagged', 'created_at'], 'idx_reviews_moderated_flagged_date');

        // ── Reels: WHERE is_active=1 ORDER BY display_order ASC ──
        $this->addCompositeIndex('reels', ['is_active', 'display_order'], 'idx_reels_active_order');

        // ── Curated Looks: WHERE is_active=1 ORDER BY display_order ASC ──
        $this->addCompositeIndex('curated_looks', ['is_active', 'display_order'], 'idx_curated_looks_active_order');

        // ── Promotions: WHERE is_active=1 ORDER BY created_at DESC ──
        $this->addCompositeIndex('promotions', ['is_active', 'created_at'], 'idx_promotions_active_created');
    }

    public function down(): void
    {
        $indexes = [
            'banners'       => 'idx_banners_active_type_position',
            'products'      => ['idx_products_published_featured_date', 'idx_products_published_views'],
            'reviews'       => 'idx_reviews_moderated_flagged_date',
            'reels'         => 'idx_reels_active_order',
            'curated_looks' => 'idx_curated_looks_active_order',
            'promotions'    => 'idx_promotions_active_created',
        ];

        foreach ($indexes as $table => $names) {
            if (!Schema::hasTable($table)) continue;
            foreach ((array) $names as $index) {
                if ($this->indexExists($table, $index)) {
                    try {
                        Schema::table($table, fn(Blueprint $t) => $t->dropIndex($index));
                    } catch (\Exception $e) {
                        // Ignore errors on rollback
                    }
                }
            }
        }
    }

    private function addCompositeIndex(string $table, array $columns, string $indexName): void
    {
        if (!Schema::hasTable($table)) return;
        if ($this->indexExists($table, $indexName)) return;
        Schema::table($table, fn(Blueprint $t) => $t->index($columns, $indexName));
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $result = DB::select("
                SELECT COUNT(*) as cnt
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND INDEX_NAME = ?
            ", [$table, $indexName]);
            return !empty($result) && $result[0]->cnt > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};
