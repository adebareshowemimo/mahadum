<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained('contact_lists')->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            // subscribed | unsubscribed | bounced
            $table->string('status')->default('subscribed');
            // Where it came from, e.g. upload | manual | signup.
            $table->string('source')->nullable();
            $table->dateTime('unsubscribed_at')->nullable();
            $table->dateTime('consent_at')->nullable();
            $table->timestamps();

            $table->unique(['contact_list_id', 'email']);
            $table->index('email');
        });

        // Global suppression — an address here is never emailed marketing again
        // (unsubscribes, hard bounces, complaints). Transactional mail may still send.
        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            // unsubscribe | bounce | complaint | manual
            $table->string('reason')->default('unsubscribe');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('contact_lists');
    }
};
