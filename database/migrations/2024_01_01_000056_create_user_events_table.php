<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->string('event_type');
            $table->string('event_name');
            $table->string('category')->nullable();
            $table->string('label')->nullable();
            $table->string('value')->nullable();
            $table->string('url')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamps();

            $table->index('user_id');
            $table->index('session_id');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_events');
    }
};
