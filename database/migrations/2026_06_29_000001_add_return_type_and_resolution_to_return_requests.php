<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->enum('return_type', [
                'exchange',     // size exchange
                'replacement',  // defective replacement
                'refund',       // cash refund to payment method
                'store_credit', // store credit / wallet
                'other',
            ])->nullable()->after('description');

            $table->enum('resolution', [
                'exchange',      // size exchanged
                'replacement',   // item replaced
                'refund',        // cash refund processed
                'store_credit',  // store credit issued
                'rejected',      // request rejected
            ])->nullable()->after('admin_response');
        });
    }

    public function down()
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropColumn(['return_type', 'resolution']);
        });
    }
};
