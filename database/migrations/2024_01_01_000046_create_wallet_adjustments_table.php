<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('wallet_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wallet_id');
            $table->uuid('admin_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('reason')->nullable();
            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('users');
            $table->timestamps();

            $table->index('wallet_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wallet_adjustments');
    }
};
