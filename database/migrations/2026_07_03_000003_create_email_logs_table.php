<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('to_email')->index();
            // Linked identity when known (user or, later, an imported contact).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            // transactional (account/security/receipts) | marketing (campaigns).
            $table->string('type')->default('transactional');
            // Event key (e.g. purchase_receipt) or campaign:{id}.
            $table->string('source')->nullable()->index();
            $table->string('subject')->nullable();
            // queued | sent | delivered | bounced | complained | failed.
            $table->string('status')->default('sent');
            $table->text('error')->nullable();
            // ESP message id — correlation key for bounce/complaint webhooks.
            $table->string('message_id')->nullable()->index();
            $table->dateTime('queued_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
