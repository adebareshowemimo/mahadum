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

    public function test_video_invitation_opens_the_exact_video_without_a_speaking_activity(): void
    {
        Notification::fake();
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $recipient = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $first = $lesson->components->firstWhere('type', 'video');
        $first->video->update(['external_url' => 'https://example.com/first-video.mp4']);
        $video = $lesson->components()->create(['type' => 'video', 'position' => 4, 'xp_value' => 0]);
        $video->video()->create(['title' => 'Tone demonstration', 'source_type' => 'youtube',
            'external_url' => 'https://www.youtube.com/watch?v=abcdefghijk', 'status' => 'ready', 'kind' => 'lesson']);
        $this->postJson('/api/v1/tone-practice/invitations', [
            'learner_id' => $learner->id, 'component_id' => $video->id, 'recipient_email' => $recipient->email,
        ])->assertCreated();
        $mail = Notification::sent($recipient, TonePracticeInvitationNotification::class)->first()->toMail($recipient);
        $this->assertSame('Watch the video and practice', $mail->actionText);
        $this->assertStringContainsString($lesson->title, implode(' ', $mail->introLines));
        $token = basename(parse_url($mail->actionUrl, PHP_URL_PATH));
        $this->actingAsUser($recipient);
        $this->getJson("/api/v1/tone-practice/invitations/{$token}")->assertOk()
            ->assertJsonPath('data.video_url', 'https://www.youtube.com/watch?v=abcdefghijk')
            ->assertJsonPath('data.lesson_title', $lesson->title)
            ->assertJsonMissingPath('data.learner_id');
        $this->postJson("/api/v1/tone-practice/invitations/{$token}/accept")->assertOk()
            ->assertJsonPath('data.accepted', true);
    }
}
