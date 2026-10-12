<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_documents', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('kind', 20)->default('page');
            $table->string('title', 60);
            $table->string('description', 155);
            $table->string('heading');
            $table->text('intro')->nullable();
            $table->text('body')->nullable();
            $table->string('author')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->json('content')->nullable();
            $table->timestamps();
        });
        foreach (json_decode(file_get_contents(base_path('web/src/lib/seo/pages.json')), true, flags: JSON_THROW_ON_ERROR) as $path => $meta) {
            $language = ['igbo' => 'Igbo', 'yoruba' => 'Yorùbá', 'hausa' => 'Hausa'][substr($path, 7)] ?? null;
            DB::table('public_documents')->insert([
                'path' => $path, 'kind' => $language ? 'language' : 'page',
                'title' => $meta['title'], 'description' => $meta['description'],
                'heading' => $language ? 'Learn '.$language.' online for kids and beginners' : '',
                'intro' => $language ? 'Learn '.$language.' with five-minute lessons, native voices and stories. For families at home and abroad, and schools. Start free with Level 0.' : '',
                'published' => true, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                'content' => json_encode(['phrases' => [], 'faqs' => [], 'videos' => [], 'levels' => [], 'language' => $language ? substr($path, 7) : null]),
            ]);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_language', 20)->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
        });
        Schema::create('language_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('language', 60)->index();
            $table->string('other_language', 60)->nullable();
            $table->timestamps();
        });
        Schema::create('discovery_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_events');
        Schema::dropIfExists('language_votes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['preferred_language', 'utm_source', 'utm_medium', 'utm_campaign']);
        });
        Schema::dropIfExists('public_documents');
    }
};
