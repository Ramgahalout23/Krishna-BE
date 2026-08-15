<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->unique();
            $table->string('transaction_id')->nullable()->unique();
            $table->enum('method', ['STRIPE', 'RAZORPAY', 'PAYPAL', 'COD', 'CUSTOM']);
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('USD');
            $table->enum('status', ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'REFUNDED'])->default('PENDING');
            $table->json('gateway_response')->nullable();
            $table->json('metadata')->nullable();
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->timestamps();

            $table->index('status');
            $table->index('transaction_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
