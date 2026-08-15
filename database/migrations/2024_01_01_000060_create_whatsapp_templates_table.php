<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->longText('body');
            $table->string('header')->nullable();
            $table->string('footer')->nullable();
            $table->longText('buttons')->nullable();
            $table->string('language')->default('en');
            $table->string('status')->default('APPROVED');
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
