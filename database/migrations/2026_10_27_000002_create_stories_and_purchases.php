<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('age_band', 20);
            $table->string('format', 20);
            $table->unsignedInteger('price_minor')->default(50000);
            $table->string('cover_path')->nullable();
            $table->string('preview_video_path')->nullable();
            $table->string('full_video_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['published_at', 'age_band']);
        });

        Schema::create('story_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->unique(['story_id', 'position']);
        });

        Schema::create('story_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('amount_minor');
            $table->string('reference')->unique();
            $table->string('gateway_txn_ref')->nullable();
            $table->string('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'story_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_purchases');
        Schema::dropIfExists('story_pages');
        Schema::dropIfExists('stories');
    }
};
