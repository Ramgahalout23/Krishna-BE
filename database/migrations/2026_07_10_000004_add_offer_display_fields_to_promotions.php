<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (!Schema::hasColumn('promotions', 'offer_badge')) {
                $table->string('offer_badge')->nullable()->after('coupon_code')
                    ->comment('Badge text for store offer cards (e.g. "BUY 2", "FREE GIFT")');
            }
            if (!Schema::hasColumn('promotions', 'offer_highlight')) {
                $table->string('offer_highlight')->nullable()->after('offer_badge')
                    ->comment('Big highlight text for store offer cards (e.g. "GET 10% OFF", "EXTRA 10% OFF")');
            }
            if (!Schema::hasColumn('promotions', 'offer_tagline')) {
                $table->string('offer_tagline')->nullable()->after('offer_highlight')
                    ->comment('Small tagline for store offer cards (e.g. "Auto-applied at checkout")');
            }
            if (!Schema::hasColumn('promotions', 'offer_theme')) {
                $table->string('offer_theme')->nullable()->after('offer_tagline')
                    ->comment('Color theme for store offer cards (e.g. "smart-deal", "prepaid-offer", "summer-bonus")');
            }
        });
    }

    public function down()
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['offer_badge', 'offer_highlight', 'offer_tagline', 'offer_theme']);
        });
    }
};
