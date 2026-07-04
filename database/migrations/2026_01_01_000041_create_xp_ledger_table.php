<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xp_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();
            $table->integer('amount');
            $table->string('source');
            $table->string('reference_type')->nullable();
            $table->bigInteger('reference_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_ledger');
    }
};
