<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('abandoned_carts', function (Blueprint $table) {
            if (!Schema::hasColumn('abandoned_carts', 'reminded_at')) {
                $table->timestamp('reminded_at')->nullable()->after('reminder_sent');
            }
            if (!Schema::hasColumn('abandoned_carts', 'recovered')) {
                $table->boolean('recovered')->default(false)->after('reminded_at');
            }
        });
    }

    public function down()
    {
        Schema::table('abandoned_carts', function (Blueprint $table) {
            $table->dropColumn(['reminded_at', 'recovered']);
        });
    }
};
