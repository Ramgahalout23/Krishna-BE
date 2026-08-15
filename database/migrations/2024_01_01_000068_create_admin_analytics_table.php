<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admin_analytics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->longText('data');
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_analytics');
    }
};
