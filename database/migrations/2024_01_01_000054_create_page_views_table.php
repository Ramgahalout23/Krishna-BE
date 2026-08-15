<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->string('url');
            $table->string('title')->nullable();
            $table->string('referrer')->nullable();
            $table->integer('duration')->default(0);
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device')->nullable();
            $table->json('metadata')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamps();

            $table->index('user_id');
            $table->index('session_id');
            $table->index('url');
            $table->index('created_at');
            $table->index(['session_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('page_views');
    }
};
