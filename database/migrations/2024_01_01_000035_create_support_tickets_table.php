<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->text('description');
            $table->enum('category', ['ORDER', 'PAYMENT', 'SHIPPING', 'PRODUCT', 'REFUND', 'ACCOUNT', 'TECHNICAL', 'OTHER']);
            $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])->default('MEDIUM');
            $table->enum('status', ['OPEN', 'IN_PROGRESS', 'WAITING_CUSTOMER', 'RESOLVED', 'CLOSED'])->default('OPEN');
            $table->uuid('user_id');
            $table->uuid('order_id')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('priority');
        });
    }

    public function down()
    {
        Schema::dropIfExists('support_tickets');
    }
};
