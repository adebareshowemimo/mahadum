<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_component_id')->constrained('lesson_components')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->decimal('pass_threshold', 12, 2)->default(0.6);
            $table->boolean('shuffle_questions')->default(false);
            $table->unsignedInteger('max_attempts')->nullable();
            $table->boolean('hearts_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
