<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_answer_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_id', 128);
            $table->char('fingerprint', 64);
            $table->json('response');
            $table->timestamps();
            $table->unique(['learner_profile_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answer_receipts');
    }
};
