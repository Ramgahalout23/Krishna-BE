<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('bundle_discount', 10, 2)->default(0)->after('discount');
            $table->decimal('flash_sale_discount', 10, 2)->default(0)->after('bundle_discount');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['bundle_discount', 'flash_sale_discount']);
        });
    }
};
