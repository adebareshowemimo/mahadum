<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('signup_account_type')->nullable());
        Schema::create('teacher_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('email');
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_invitations');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('signup_account_type'));
    }
};
