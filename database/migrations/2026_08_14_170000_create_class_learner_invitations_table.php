<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_learner_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['school_class_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_learner_invitations');
    }
};
