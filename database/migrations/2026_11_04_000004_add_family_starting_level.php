<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_profiles', fn (Blueprint $table) => $table->unsignedTinyInteger('starting_level_position')->nullable());
    }

    public function down(): void
    {
        Schema::table('learner_profiles', fn (Blueprint $table) => $table->dropColumn('starting_level_position'));
    }
};
