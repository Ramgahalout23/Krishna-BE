<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->longText('content_html');
            $table->longText('content_text')->nullable();
            $table->enum('type', ['EMAIL', 'PUSH'])->default('EMAIL');
            $table->enum('status', ['DRAFT', 'SCHEDULED', 'SENDING', 'SENT', 'FAILED', 'PAUSED', 'CANCELLED'])->default('DRAFT');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->integer('bounced_count')->default(0);
            $table->integer('unsubscribed_count')->default(0);
            $table->integer('complained_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
            $table->index('scheduled_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('campaigns');
    }
};
