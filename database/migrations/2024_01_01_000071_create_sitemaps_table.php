<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sitemaps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('url')->unique();
            $table->dateTime('last_modified')->nullable();
            $table->timestamps();

            $table->index('url');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sitemaps');
    }
};
