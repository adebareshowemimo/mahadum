<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_roster_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('identity_key', 64);
            $table->string('batch_key', 64);
            $table->foreignId('learner_profile_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'identity_key'], 'school_roster_identity_unique');
            $table->index(['organization_id', 'batch_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_roster_identities');
    }
};
