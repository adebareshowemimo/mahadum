<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xapi_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();
            $table->string('verb');
            $table->string('object_iri')->nullable();
            $table->json('raw')->nullable();
            $table->dateTime('lrs_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xapi_statements');
    }
};
