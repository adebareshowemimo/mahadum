<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_practice_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->constrained('users');
            $table->timestamps();
            $table->unique(['learner_profile_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_practice_partners');
    }
};
