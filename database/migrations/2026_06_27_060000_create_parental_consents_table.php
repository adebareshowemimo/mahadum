<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('learner_profile_id')->nullable()->constrained('learner_profiles')->cascadeOnDelete();
            $table->string('type'); // coppa_parental | data_processing
            $table->string('policy_version');
            $table->dateTime('granted_at');
            $table->string('ip')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['family_id', 'learner_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parental_consents');
    }
};
