<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Public voting. One vote per user per category per competition — enforced
        // by the composite unique index (category + competition redundantly stored
        // on the vote so the constraint can be expressed at the DB level).
        Schema::create('competition_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('competition_entry_id')->constrained('competition_entries')->cascadeOnDelete();
            $table->string('category');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['competition_id', 'category', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_votes');
    }
};
