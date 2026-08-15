<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('message')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_completed')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_days')->nullable();
            $table->string('time_start')->nullable();
            $table->string('time_end')->nullable();
            $table->dateTime('last_activated_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
