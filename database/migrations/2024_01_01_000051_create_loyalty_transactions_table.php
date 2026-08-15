<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loyalty_id');
            $table->string('type');
            $table->integer('points');
            $table->string('reason');
            $table->string('reference_id')->nullable();
            $table->foreign('loyalty_id')->references('id')->on('loyalty_points')->onDelete('cascade');
            $table->timestamps();

            $table->index('loyalty_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
