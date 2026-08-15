<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE settings MODIFY COLUMN module ENUM(
            'SITE', 'THEME', 'PAYMENT', 'SHIPPING', 'TAX', 'SMTP', 'SOCIAL',
            'CURRENCY', 'LANGUAGE', 'CONTACT', 'WEBSOCKET', 'MARKETING', 'ADS',
            'SYSTEM'
        ) NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE settings MODIFY COLUMN module ENUM(
            'SITE', 'THEME', 'PAYMENT', 'SHIPPING', 'TAX', 'SMTP', 'SOCIAL',
            'CURRENCY', 'LANGUAGE', 'CONTACT', 'WEBSOCKET', 'MARKETING', 'ADS'
        ) NOT NULL");
    }
};
