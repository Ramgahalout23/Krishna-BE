<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('campaign_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['PROMOTIONAL', 'NEWSLETTER', 'WELCOME', 'SEASONAL', 'ABANDONED_CART', 'ORDER_CONFIRMATION', 'FOLLOW_UP', 'CUSTOM'])->default('CUSTOM');
            $table->string('thumbnail')->nullable();
            $table->longText('content_html');
            $table->longText('variables')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'DRAFT'])->default('DRAFT');
            $table->boolean('is_default')->default(false);
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('campaign_templates');
    }
};
