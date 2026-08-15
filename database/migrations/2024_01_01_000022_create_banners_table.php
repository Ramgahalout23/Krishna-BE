<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url');
            $table->string('link_url')->nullable();
            $table->enum('type', ['HERO', 'SALE', 'CATEGORY', 'POPUP', 'FEATURED', 'NEW_ARRIVAL']);
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->boolean('show_on_mobile')->default(true);
            $table->boolean('show_on_desktop')->default(true);
            $table->string('background_color')->nullable();
            $table->string('text_color')->nullable();
            $table->string('cta')->nullable();
            $table->string('align')->default('center');
            $table->boolean('text_dark')->default(false);
            $table->string('display_mode')->default('DEFAULT');
            $table->string('button_text')->nullable();
            $table->string('button_link')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('position');
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('banners');
    }
};
