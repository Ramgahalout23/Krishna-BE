<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shippings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->unique();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable()->unique();
            $table->decimal('cost', 10, 2)->default(0);
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamp('actual_delivery')->nullable();
            $table->string('status')->default('PENDING');
            $table->text('notes')->nullable();
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->timestamps();

            $table->index('status');
            $table->index('tracking_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('shippings');
    }
};
