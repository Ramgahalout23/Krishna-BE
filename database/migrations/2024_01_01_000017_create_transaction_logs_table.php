<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transaction_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->string('action');
            $table->string('status');
            $table->text('message')->nullable();
            $table->longText('metadata')->nullable();
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->timestamps();

            $table->index('payment_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transaction_logs');
    }
};
