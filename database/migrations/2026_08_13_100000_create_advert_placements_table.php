<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advert_placements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position'); // leaderboard|inline
            $table->string('size')->nullable(); // e.g. '970x250', '728x90', '300x250' (informational)
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('target_url');
            $table->boolean('is_active')->default(false);
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->timestamps();

            $table->index(['position', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advert_placements');
    }
};
