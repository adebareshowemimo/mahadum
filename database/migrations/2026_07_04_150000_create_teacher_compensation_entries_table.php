<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_compensation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('period'); // 'YYYY-MM', the accrual month
            $table->unsignedInteger('paying_student_count');
            $table->unsignedInteger('rate_minor'); // rate snapshot at accrual time
            $table->unsignedInteger('amount_minor');
            $table->timestamps();

            $table->unique(['teacher_user_id', 'organization_id', 'period'], 'teacher_compensation_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_compensation_entries');
    }
};
