<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('content');
            $table->boolean('is_from_admin')->default(false);
            $table->uuid('ticket_id');
            $table->uuid('sender_id');
            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');
            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_messages');
    }
};
