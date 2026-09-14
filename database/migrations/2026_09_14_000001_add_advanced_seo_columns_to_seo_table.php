<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `seo` table was created with only the basic meta fields, but the Seo
 * model, AdvancedSeoService (audits, schema generation, IndexNow) and the admin
 * SEO dashboard all read/write the advanced columns below. Every one of those
 * features was failing with "Unknown column 'seo_score'" — the SEO dashboard
 * returned a 500 and the page could never load.
 *
 * Each column is guarded with hasColumn() so this migration is safe to run on
 * databases where some of them already exist.
 */
return new class extends Migration
{
    /**
     * Columns to add, keyed by name => [type, nullable].
     */
    private function columns(): array
    {
        return [
            // ── Audit results (written by AdvancedSeoService::auditEntitySEO) ──
            'seo_score' => ['integer', true],
            'seo_score_breakdown' => ['json', true],
            'seo_last_audited_at' => ['dateTime', true],

            // ── IndexNow push state ──
            'indexnow_pushed_at' => ['dateTime', true],
            'indexnow_pending' => ['boolean', false],

            // ── Structured data / schema output ──
            'json_ld_product' => ['json', true],
            'json_ld_organization' => ['json', true],
            'json_ld_breadcrumb' => ['json', true],
            'json_ld_faq' => ['json', true],
            'json_ld_website' => ['json', true],

            // ── Internationalisation & crawl hints ──
            'hreflang_tags' => ['json', true],
            'breadcrumb_path' => ['json', true],
            'content_language' => ['string', true],
            'robots_meta' => ['string', true],
            'cache_control' => ['string', true],

            // ── Analytics identifiers ──
            'facebook_pixel_id' => ['string', true],
            'google_analytics_id' => ['string', true],
            'google_tag_manager_id' => ['string', true],

            // ── Sitemap hints ──
            'sitemap_priority' => ['float', true],
            'sitemap_changefreq' => ['string', true],
        ];
    }

    public function up()
    {
        $addedScoreColumn = false;

        foreach ($this->columns() as $name => [$type, $nullable]) {
            if (Schema::hasColumn('seo', $name)) {
                continue;
            }

            Schema::table('seo', function (Blueprint $table) use ($name, $type, $nullable) {
                $column = match ($type) {
                    'integer' => $table->integer($name),
                    'boolean' => $table->boolean($name),
                    'dateTime' => $table->dateTime($name),
                    'float' => $table->float($name),
                    'json' => $table->json($name),
                    default => $table->string($name, 255),
                };

                if ($nullable) {
                    $column->nullable();
                } elseif ($type === 'boolean') {
                    $column->default(false);
                }
            });

            if ($name === 'seo_score') {
                $addedScoreColumn = true;
            }
        }

        // The dashboard aggregates and orders by score — keep those scans
        // index-backed, but only when this migration created the column (a
        // pre-existing column may already carry an index of its own).
        if ($addedScoreColumn) {
            Schema::table('seo', function (Blueprint $table) {
                $table->index('seo_score');
            });
        }
    }

    public function down()
    {
        foreach (array_keys($this->columns()) as $name) {
            if (!Schema::hasColumn('seo', $name)) {
                continue;
            }
            Schema::table('seo', function (Blueprint $table) use ($name) {
                $table->dropColumn($name);
            });
        }
    }
};
