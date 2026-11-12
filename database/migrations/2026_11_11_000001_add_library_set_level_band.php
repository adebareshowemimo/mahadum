<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_sets', function (Blueprint $table) {
            $table->string('level_band', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('library_sets', function (Blueprint $table) {
            $table->dropColumn('level_band');
        });
    }
};
