<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reel_likes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('reel_id')->constrained('reels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'reel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reel_likes');
    }
};
