<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_deck_id')->constrained('exercise_decks')->cascadeOnDelete();
            $table->string('front_text');
            $table->string('back_text');
            $table->foreignId('image_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('audio_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('mnemonic')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcards');
    }
};
