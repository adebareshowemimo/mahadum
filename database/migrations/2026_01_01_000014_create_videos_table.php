<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_component_id')->nullable()->constrained('lesson_components')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('presenter_name')->nullable();
            $table->boolean('is_cultural')->default(false);
            $table->string('kind')->default('lesson');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->foreignId('source_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('poster_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('default_quality')->default('360p');
            $table->string('status')->default('uploading');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
