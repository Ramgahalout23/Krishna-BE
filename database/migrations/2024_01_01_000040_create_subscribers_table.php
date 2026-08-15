<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->enum('status', ['SUBSCRIBED', 'UNSUBSCRIBED', 'BOUNCED', 'COMPLAINED'])->default('SUBSCRIBED');
            $table->enum('source', ['SIGNUP', 'IMPORT', 'ADMIN', 'CHECKOUT'])->default('SIGNUP');
            $table->string('tags')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('status');
            $table->index('source');
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscribers');
    }
};
