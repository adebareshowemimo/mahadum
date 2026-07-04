<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_template_overrides', function (Blueprint $table) {
            $table->id();
            // Matches a key in config('email_templates') — not a foreign key since
            // the registry lives in code, not the DB.
            $table->string('key')->unique();
            $table->string('subject');
            $table->string('greeting')->nullable();
            $table->text('body'); // paragraphs separated by a blank line
            $table->string('action_text')->nullable();
            $table->string('action_url')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_template_overrides');
    }
};
