<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A submission into a competition. Two categories:
        //  • school_play        — a school enacts a culturally relevant play.
        //  • diaspora_folklore  — a diaspora child presents a story in their
        //                         local language (folklore category).
        Schema::create('competition_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->string('category'); // school_play | diaspora_folklore
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('learner_profile_id')->nullable()->constrained('learner_profiles')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('synopsis')->nullable();
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            // submitted → approved / rejected / disqualified.
            $table->string('status')->default('submitted');
            $table->unsignedInteger('votes_count')->default(0);
            $table->unsignedTinyInteger('award_rank')->nullable(); // set at judging (1 = winner)
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_entries');
    }
};
