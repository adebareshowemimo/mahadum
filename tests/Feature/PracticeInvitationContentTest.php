<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CoursePracticeInvitation;
use App\Models\TonePracticeInvitation;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class PracticeInvitationContentTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public static function withdrawnContent(): array
    {
        return [['course', false], ['course', true], ['tone', false], ['tone', true]];
    }

    public static function invitationKinds(): array
    {
        return [['course'], ['tone']];
    }

    #[DataProvider('invitationKinds')]
    public function test_failed_acceptance_audit_does_not_consume_the_invitation(string $kind): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $model = $kind === 'course' ? CoursePracticeInvitation::class : TonePracticeInvitation::class;
        $target = $kind === 'course' ? ['lesson_id' => $lesson->id]
            : ['lesson_component_id' => $lesson->components->firstWhere('type', 'speaking')->id];
        $token = 'atomic-acceptance-token';
        $invitation = $model::create([...$target, 'learner_profile_id' => $learner->id,
            'inviter_user_id' => $parent->id, 'recipient_user_id' => $parent->id,
            'token_hash' => hash('sha256', $token), 'channel' => 'email', 'expires_at' => now()->addHours(48)]);
        $this->actingAsUser($parent);
        $this->mock(AuditLogger::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Simulated audit failure'));
        $url = "/api/v1/{$kind}-practice/invitations/{$token}/accept";
        $this->postJson($url)->assertStatus(500);
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertNull($invitation->fresh()->opened_at);
        $this->app->instance(AuditLogger::class, new AuditLogger);
        $this->postJson($url)->assertOk()->assertJsonPath('data.accepted', true);
        $this->postJson($url)->assertOk();
        $this->assertSame(1, AuditLog::where('action', "{$kind}_practice.accepted")->count());
    }

    #[DataProvider('withdrawnContent')]
    public function test_withdrawn_or_deleted_lessons_cannot_be_opened_or_accepted(string $kind, bool $deleted): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $recipient = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $model = $kind === 'course' ? CoursePracticeInvitation::class : TonePracticeInvitation::class;
        $target = $kind === 'course' ? ['lesson_id' => $lesson->id]
            : ['lesson_component_id' => $lesson->components->firstWhere('type', 'speaking')->id];
        $token = 'withdrawn-content-token';
        $invitation = $model::create([
            ...$target, 'learner_profile_id' => $learner->id, 'inviter_user_id' => $parent->id,
            'recipient_user_id' => $recipient->id, 'token_hash' => hash('sha256', $token),
            'channel' => 'email', 'expires_at' => now()->addHours(48),
        ]);
        $deleted ? $lesson->delete() : $lesson->update(['published_at' => null]);
        $this->actingAsUser($recipient);
        $this->getJson("/api/v1/{$kind}-practice/invitations/{$token}")->assertGone()
            ->assertJsonMissingPath('data.video_url')->assertJsonMissingPath('data.lesson_title');
        $this->postJson("/api/v1/{$kind}-practice/invitations/{$token}/accept")->assertGone();
        $this->assertNull($invitation->fresh()->opened_at);
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseMissing('audit_logs', ['action' => "{$kind}_practice.accepted"]);
    }

    public function test_home_invitation_skips_an_unpublished_path_lesson(): void
    {
        Notification::fake();
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $next = $lesson->courseLevel->lessons()->create(['title' => 'Published next lesson', 'position' => 2, 'published_at' => now()]);
        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $lesson->courseLevel->course_id])->assertCreated();
        $lesson->update(['published_at' => null]);

        $this->postJson('/api/v1/course-practice/invitations', ['learner_id' => $learner->id, 'recipient_user_id' => $parent->id])->assertCreated();
        $this->assertDatabaseHas('course_practice_invitations', ['learner_profile_id' => $learner->id, 'lesson_id' => $next->id]);
    }

    public function test_no_published_path_lesson_rejects_without_creating_an_invitation_or_sending_mail(): void
    {
        Notification::fake();
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $lesson->courseLevel->course_id])->assertCreated();
        $lesson->update(['published_at' => null]);

        $this->postJson('/api/v1/course-practice/invitations', ['learner_id' => $learner->id, 'recipient_user_id' => $parent->id])->assertUnprocessable();
        $this->assertDatabaseCount('course_practice_invitations', 0);
        Notification::assertNothingSent();
    }
}
