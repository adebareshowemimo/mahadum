<?php

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            // Null author = system note; is_staff distinguishes admin replies from the requester.
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_staff')->default(false);
            $table->text('body');
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
        });

        // Backfill: fold any existing single admin `response` into the thread so no
        // history is lost when the UI switches to a message list.
        SupportTicket::whereNotNull('response')->get()->each(function (SupportTicket $t) {
            SupportTicketMessage::create([
                'ticket_id' => $t->id,
                'author_id' => $t->assigned_to,
                'is_staff' => true,
                'body' => (string) $t->response,
                'created_at' => $t->resolved_at ?? $t->updated_at ?? $t->created_at,
                'updated_at' => $t->resolved_at ?? $t->updated_at ?? $t->created_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
    }
};
