<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add traffic source columns to user_sessions
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->string('source')->nullable()->after('referrer');
            $table->string('utm_source')->nullable()->after('source');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('utm_term')->nullable()->after('utm_campaign');
            $table->string('utm_content')->nullable()->after('utm_term');
        });

        // Add traffic source columns to page_views
        Schema::table('page_views', function (Blueprint $table) {
            $table->string('source')->nullable()->after('referrer');
            $table->string('utm_source')->nullable()->after('source');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('utm_term')->nullable()->after('utm_campaign');
            $table->string('utm_content')->nullable()->after('utm_term');
        });

        // Add indexes for analytics queries
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->index('source', 'idx_sessions_source');
        });

        Schema::table('page_views', function (Blueprint $table) {
            $table->index('source', 'idx_pageviews_source');
        });
    }

    public function down()
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_sessions_source');
            $table->dropColumn(['source', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']);
        });

        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex('idx_pageviews_source');
            $table->dropColumn(['source', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']);
        });
    }
};
