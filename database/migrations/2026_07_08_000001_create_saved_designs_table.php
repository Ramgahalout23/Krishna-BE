<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('saved_designs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name', 100)->nullable();
            $table->string('color', 50);
            $table->string('size', 10);
            $table->string('design_id', 50);
            $table->string('accent_color', 7)->nullable();
            $table->json('design_data')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
            $table->index('updated_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('saved_designs');
    }
};
