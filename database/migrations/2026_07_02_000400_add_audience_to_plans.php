<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Who a plan is aimed at — drives which surface offers it (consumer
            // paywall vs school console) and lets admins group tiers.
            $table->string('audience')->nullable()->after('interval');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
