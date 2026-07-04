<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            // Coins escrowed on submission and released to the learner on parent approval.
            $table->unsignedInteger('coin_reward')->default(0)->after('max_duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('coin_reward');
        });
    }
};
