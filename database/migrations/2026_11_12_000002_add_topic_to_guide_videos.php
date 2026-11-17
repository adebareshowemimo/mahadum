<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_videos', function (Blueprint $table) {
            $table->string('topic', 30)->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('guide_videos', function (Blueprint $table) {
            $table->dropColumn('topic');
        });
    }
};
