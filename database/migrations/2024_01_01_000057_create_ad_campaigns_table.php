<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('platform', ['INSTAGRAM', 'FACEBOOK', 'WHATSAPP', 'GOOGLE', 'CUSTOM']);
            $table->string('objective')->nullable();
            $table->longText('target_audience')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->decimal('spent', 12, 2)->default(0);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->enum('status', ['DRAFT', 'SCHEDULED', 'ACTIVE', 'PAUSED', 'COMPLETED', 'FAILED', 'CANCELLED'])->default('DRAFT');
            $table->string('creative_url')->nullable();
            $table->string('creative_type')->nullable();
            $table->string('landing_url')->nullable();
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('conversions')->default(0);
            $table->integer('reach')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('cpc', 10, 4)->default(0);
            $table->longText('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('platform_campaign_id')->nullable();
            $table->string('platform_adset_id')->nullable();
            $table->string('platform_creative_id')->nullable();
            $table->string('platform_ad_id')->nullable();
            $table->string('platform_status')->nullable();
            $table->string('platform_url')->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('platform');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
