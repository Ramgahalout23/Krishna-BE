<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('module', ['SITE', 'THEME', 'PAYMENT', 'SHIPPING', 'TAX', 'SMTP', 'SOCIAL', 'CURRENCY', 'LANGUAGE', 'CONTACT', 'WEBSOCKET', 'MARKETING', 'ADS']);
            $table->string('key');
            $table->text('value');
            $table->timestamps();

            $table->unique(['module', 'key']);
            $table->index('key');
        });
    }

    public function down()
    {
        Schema::dropIfExists('settings');
    }
};
