<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ad_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id')->nullable();
            $table->string('platform')->nullable();
            $table->string('suggestion_type');
            $table->longText('original_prompt');
            $table->longText('generated_content');
            $table->longText('metadata')->nullable();
            $table->boolean('was_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->foreign('campaign_id')->references('id')->on('ad_campaigns')->onDelete('cascade');
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('suggestion_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ad_suggestions');
    }
};
