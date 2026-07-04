<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('component_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_progress_id')->constrained('lesson_progress')->cascadeOnDelete();
            $table->foreignId('lesson_component_id')->constrained('lesson_components')->cascadeOnDelete();
            $table->string('status')->default('in_progress');
            $table->decimal('score', 12, 2)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_progress');
    }
};
