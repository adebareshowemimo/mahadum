<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chore_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chore_id')->constrained('chores')->cascadeOnDelete();
            $table->foreignId('evidence_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('evidence_type')->default('checkbox');
            $table->dateTime('submitted_at')->nullable();
            $table->string('decision')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chore_submissions');
    }
};
