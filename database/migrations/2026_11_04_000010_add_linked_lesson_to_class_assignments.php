<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_assignments', function (Blueprint $table) {
            $table->foreignId('linked_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_lesson_id');
        });
    }
};
