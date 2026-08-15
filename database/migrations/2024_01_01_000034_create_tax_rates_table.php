<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->decimal('rate', 5, 2);
            $table->enum('type', ['PERCENTAGE', 'FLAT'])->default('PERCENTAGE');
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_pattern')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['country', 'state']);
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tax_rates');
    }
};
