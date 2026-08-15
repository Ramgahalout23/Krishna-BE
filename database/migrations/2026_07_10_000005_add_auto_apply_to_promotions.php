<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (!Schema::hasColumn('promotions', 'auto_apply')) {
                $table->boolean('auto_apply')->default(false)->after('offer_theme')
                    ->comment('When true, this promotion auto-applies as a store-wide discount to all cart items at checkout');
            }
        });
    }

    public function down()
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('auto_apply');
        });
    }
};
