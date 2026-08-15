<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Seo extends Model
{
    use HasUuids;
    protected $table = 'seo';
    protected $fillable = [
        'entity_type', 'entity_id', 'meta_title', 'meta_description', 'meta_keywords',
        'og_title', 'og_description', 'og_image', 'twitter_title', 'twitter_description',
        'twitter_image', 'canonical_url', 'slug',
        // Advanced SEO fields
        'json_ld_product', 'json_ld_organization', 'json_ld_breadcrumb',
        'json_ld_faq', 'json_ld_website', 'hreflang_tags', 'breadcrumb_path',
        'facebook_pixel_id', 'google_analytics_id', 'google_tag_manager_id',
        'robots_meta', 'cache_control', 'content_language',
        'seo_score', 'seo_score_breakdown', 'seo_last_audited_at',
        'sitemap_priority', 'sitemap_changefreq',
    ];

    protected $casts = [
        'json_ld_product' => 'array',
        'json_ld_organization' => 'array',
        'json_ld_breadcrumb' => 'array',
        'json_ld_faq' => 'array',
        'json_ld_website' => 'array',
        'hreflang_tags' => 'array',
        'breadcrumb_path' => 'array',
        'seo_score_breakdown' => 'array',
        'seo_last_audited_at' => 'datetime',
        'indexnow_pushed_at' => 'datetime',
        'indexnow_pending' => 'boolean',
        'sitemap_priority' => 'float',
        'seo_score' => 'integer',
    ];
}
