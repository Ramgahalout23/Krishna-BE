<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_score_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type'); // product, category, page
            $table->string('entity_id');
            $table->integer('score');
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_score_history');
    }
};
