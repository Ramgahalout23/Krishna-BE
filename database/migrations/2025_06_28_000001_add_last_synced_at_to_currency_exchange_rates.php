<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('currency_exchange_rates', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('is_active');
        });
    }

    public function down()
    {
        Schema::table('currency_exchange_rates', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
        });
    }
};
