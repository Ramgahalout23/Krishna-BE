<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('file_name');
            $table->string('file_url');
            $table->string('file_type');
            $table->integer('file_size');
            $table->uuid('ticket_id');
            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');
            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
