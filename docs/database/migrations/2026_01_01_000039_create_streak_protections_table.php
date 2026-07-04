<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streak_protections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();
            $table->string('type')->default('grace');
            $table->string('source')->default('telco_grace');
            $table->dateTime('active_from')->nullable();
            $table->dateTime('active_to')->nullable();
            $table->boolean('consumed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streak_protections');
    }
};
