<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_impressions', function (Blueprint $table) {
            // Set once the reward (e.g. a hearts refill) tied to this impression
            // has been redeemed, so a shown-and-verified ad can't be claimed twice.
            $table->timestamp('consumed_at')->nullable()->after('shown_at');
        });
    }

    public function down(): void
    {
        Schema::table('ad_impressions', function (Blueprint $table) {
            $table->dropColumn('consumed_at');
        });
    }
};
