<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('roster_level_position')->nullable();
        });
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('assigned_course_level_id')->nullable()->constrained('course_levels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_course_level_id');
        });
        Schema::table('learner_profiles', function (Blueprint $table) {
            $table->dropColumn('roster_level_position');
        });
    }
};
