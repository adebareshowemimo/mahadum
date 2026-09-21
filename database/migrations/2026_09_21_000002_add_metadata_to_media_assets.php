<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->string('title')->nullable()->after('folder')->index();
            $table->text('description')->nullable()->after('title');
            $table->json('tags')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropIndex(['title']);
            $table->dropColumn(['title', 'description', 'tags']);
        });
    }
};
