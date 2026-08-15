<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('user_id');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED', 'PARTIAL'])->default('PENDING');
            $table->string('reason');
            $table->text('description')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->boolean('refund_to_wallet')->default(false);
            $table->text('admin_response')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('return_requests');
    }
};
