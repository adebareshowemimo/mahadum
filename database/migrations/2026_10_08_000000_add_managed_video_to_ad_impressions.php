<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_impressions', function (Blueprint $table) {
            $table->json('managed_video')->nullable();
            $table->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ad_impressions', fn (Blueprint $table) => $table->dropColumn(['managed_video', 'expires_at']));
    }
};
