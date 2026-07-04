<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hearts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();
            $table->unsignedInteger('current')->default(5);
            $table->dateTime('refills_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hearts');
    }
};
