<?php

namespace Tests\Feature;

use App\Models\TonePracticeInvitation;
use App\Notifications\TonePracticeInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class TonePracticeInvitationTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_parent_can_invite_registered_parent_for_48_hours(): void
    {
        Notification::fake();
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $recipient = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        $component = $this->publishedLesson()->components->firstWhere('type', 'speaking');

        $this->postJson('/api/v1/tone-practice/invitations', [
            'learner_id' => $learner->id,
            'component_id' => $component->id,
            'recipient_email' => $recipient->email,
        ])->assertCreated()
            ->assertJsonPath('data.sent', true);

        $invitation = TonePracticeInvitation::firstOrFail();
        $this->assertTrue($invitation->expires_at->between(now()->addHours(47), now()->addHours(49)));
        Notification::assertSentTo($recipient, TonePracticeInvitationNotification::class);
    }

    public function test_only_the_registered_recipient_can_open_a_privacy_safe_invitation(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $recipient = $this->userWithRole('teacher');
        $stranger = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        $component = $this->publishedLesson()->components->firstWhere('type', 'speaking');
        $token = 'known-private-token';
        TonePracticeInvitation::create([
            'learner_profile_id' => $learner->id,
            'lesson_component_id' => $component->id,
            'inviter_user_id' => $parent->id,
            'recipient_user_id' => $recipient->id,
            'token_hash' => hash('sha256', $token),
            'channel' => 'email',
            'expires_at' => now()->addHours(48),
        ]);

        $this->actingAsUser($stranger);
        $this->getJson("/api/v1/tone-practice/invitations/{$token}")->assertForbidden();

        $this->actingAsUser($recipient);
        $response = $this->getJson("/api/v1/tone-practice/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.practice_text', 'x');
        $response->assertJsonMissingPath('data.learner_id')
            ->assertJsonMissingPath('data.learner_name')
            ->assertJsonMissingPath('data.recipient_email');
    }
}
