<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add advanced SEO settings to settings table
        // Schema changes to 'seo' table were already applied in a previous partial run
        $settings = [
            ['key' => 'seo_google_analytics_id', 'value' => '', 'module' => 'SITE'],
            ['key' => 'seo_google_tag_manager_id', 'value' => '', 'module' => 'SITE'],
            ['key' => 'seo_facebook_pixel_id', 'value' => '', 'module' => 'SITE'],
            ['key' => 'seo_organization_name', 'value' => '', 'module' => 'SITE'],
            ['key' => 'seo_organization_logo', 'value' => '', 'module' => 'SITE'],
            ['key' => 'seo_organization_url', 'value' => '', 'module' => 'SITE'],
            ['key' => 'seo_social_links', 'value' => '', 'module' => 'SITE'],
            ['key' => 'seo_hreflang_default', 'value' => 'en', 'module' => 'SITE'],
            ['key' => 'seo_enable_auto_schema', 'value' => 'true', 'module' => 'SITE'],
            ['key' => 'seo_enable_indexnow', 'value' => 'false', 'module' => 'SITE'],
            ['key' => 'seo_breadcrumb_separator', 'value' => '/', 'module' => 'SITE'],
            ['key' => 'seo_default_image', 'value' => '', 'module' => 'SITE'],
            ['key' => 'seo_twitter_handle', 'value' => '', 'module' => 'SITE'],
            ['key' => 'seo_auto_audit_enabled', 'value' => 'true', 'module' => 'SITE'],
            ['key' => 'seo_audit_schedule', 'value' => 'weekly', 'module' => 'SITE'],
        ];

        foreach ($settings as $setting) {
            \App\Models\Setting::updateOrCreate(
                ['key' => $setting['key'], 'module' => $setting['module']],
                ['value' => $setting['value']]
            );
        }
    }

    public function down()
    {
        \App\Models\Setting::where('module', 'SITE')
            ->whereIn('key', [
                'seo_google_analytics_id', 'seo_google_tag_manager_id', 'seo_facebook_pixel_id',
                'seo_organization_name', 'seo_organization_logo', 'seo_organization_url',
                'seo_social_links', 'seo_hreflang_default', 'seo_enable_auto_schema',
                'seo_enable_indexnow', 'seo_breadcrumb_separator', 'seo_default_image',
                'seo_twitter_handle', 'seo_auto_audit_enabled', 'seo_audit_schedule',
            ])
            ->delete();
    }
};
