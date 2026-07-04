<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportThreadTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(?User $owner = null): SupportTicket
    {
        return SupportTicket::create([
            'user_id' => $owner?->id,
            'email' => $owner?->email ?? 'guest@example.test',
            'channel' => 'in_app',
            'subject' => 'Cannot log in',
            'message' => 'The app keeps signing me out.',
            'status' => 'open',
            'priority' => 'normal',
        ]);
    }

    public function test_admin_lists_tickets_with_assignees(): void
    {
        $this->seedRbac();
        $this->ticket();
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->getJson('/api/v1/admin/support-tickets')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'open_count', 'assignees' => [['id', 'name']]]);
    }

    public function test_admin_reply_appends_message_and_advances_status(): void
    {
        $this->seedRbac();
        $ticket = $this->ticket();
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->postJson("/api/v1/admin/support-tickets/{$ticket->id}/messages", ['body' => 'Try clearing your cache.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.messages.0.body', 'Try clearing your cache.')
            ->assertJsonPath('data.messages.0.is_staff', true);

        $this->assertDatabaseHas('support_ticket_messages', ['ticket_id' => $ticket->id, 'is_staff' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'support.ticket_replied']);
    }

    public function test_requester_replies_on_own_ticket_and_reopens_when_resolved(): void
    {
        $this->seedRbac();
        $owner = $this->userWithRole('parent');
        $ticket = $this->ticket($owner);
        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
        $this->actingAsUser($owner);

        $this->postJson("/api/v1/support/tickets/{$ticket->id}/messages", ['body' => 'Still broken.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('support_ticket_messages', ['ticket_id' => $ticket->id, 'is_staff' => false]);
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'status' => 'open']);
    }

    public function test_requester_cannot_reply_on_foreign_ticket(): void
    {
        $this->seedRbac();
        $owner = $this->userWithRole('parent');
        $ticket = $this->ticket($owner);
        $intruder = $this->userWithRole('parent');
        $this->actingAsUser($intruder);

        $this->postJson("/api/v1/support/tickets/{$ticket->id}/messages", ['body' => 'Let me in.'])
            ->assertStatus(403);
    }
}
