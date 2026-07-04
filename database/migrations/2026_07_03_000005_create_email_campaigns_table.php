<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('body'); // markdown, rendered in the brand template
            // user_segment | contact_list
            $table->string('audience_type');
            // Segment filters {role,status,organization_id,...} or {contact_list_id}.
            $table->json('audience')->nullable();
            // draft | scheduled | sending | sent | failed
            $table->string('status')->default('draft');
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->dateTime('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('email_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('contact_id')->nullable();
            // queued | sent | failed | suppressed
            $table->string('status')->default('queued');
            $table->timestamps();

            $table->index(['email_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_recipients');
        Schema::dropIfExists('email_campaigns');
    }
};
