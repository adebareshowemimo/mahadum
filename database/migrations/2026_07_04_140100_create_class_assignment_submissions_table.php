<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_assignment_id')->constrained('class_assignments')->cascadeOnDelete();
            $table->foreignId('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('status')->default('submitted'); // submitted | graded
            $table->boolean('passed')->nullable();
            $table->unsignedTinyInteger('score')->nullable(); // 0-100, optional detail alongside pass/fail
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['class_assignment_id', 'learner_profile_id'], 'class_assignment_submissions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_assignment_submissions');
    }
};
