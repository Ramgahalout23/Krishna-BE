<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 5)->unique();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('language_code', 5);
            $table->string('group')->default('frontend');
            $table->string('key');
            $table->text('value');
            $table->timestamps();

            $table->unique(['language_code', 'group', 'key']);
            $table->foreign('language_code')->references('code')->on('languages')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('translations');
        Schema::dropIfExists('languages');
    }
};
