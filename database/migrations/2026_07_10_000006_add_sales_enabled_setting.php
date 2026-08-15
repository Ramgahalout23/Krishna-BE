<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        \App\Models\Setting::updateOrCreate(
            ['key' => 'salesEnabled', 'module' => 'SITE'],
            ['value' => 'true']
        );
    }

    public function down()
    {
        \App\Models\Setting::where('key', 'salesEnabled')
            ->where('module', 'SITE')
            ->delete();
    }
};
