<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('display_name');
            $table->unsignedInteger('avatar_id')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('age_band')->nullable();
            $table->foreignId('target_language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->unsignedInteger('current_level')->default(1);
            $table->boolean('parental_pin_protected')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_profiles');
    }
};
