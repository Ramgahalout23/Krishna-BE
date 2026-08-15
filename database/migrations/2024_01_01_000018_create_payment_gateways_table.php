<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->enum('provider', ['STRIPE', 'RAZORPAY', 'PAYPAL', 'COD', 'CUSTOM']);
            $table->string('api_key');
            $table->string('api_secret');
            $table->string('mode')->default('sandbox');
            $table->boolean('is_active')->default(true);
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_gateways');
    }
};
