<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_renditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->string('quality');
            $table->string('protocol')->default('hls');
            $table->string('manifest_url');
            $table->unsignedInteger('bitrate_kbps')->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->boolean('ready')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_renditions');
    }
};
