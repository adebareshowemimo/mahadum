<?php

namespace Tests\Feature;

use App\Models\CoursePracticeInvitation;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Notifications\CoursePracticeInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class CoursePracticeInvitationTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function enroll($learner, $course): void
    {
        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $course->id])
            ->assertCreated();
    }

    public function test_search_only_returns_family_and_school_staff_contacts(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Fam']);
        $learner = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Kid', 'current_level' => 1]);

        $coParent = $this->userWithRole('parent');
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $coParent->id, 'relationship' => 'co-parent', 'is_account_owner' => false]);

        $stranger = $this->userWithRole('parent');

        $response = $this->getJson("/api/v1/learners/{$learner->id}/practice-contacts?q=".substr($coParent->email, 0, 4))
            ->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($coParent->id, $ids);
        $this->assertNotContains($stranger->id, $ids);
    }

    public function test_parent_can_invite_a_family_contact_to_practice_the_current_lesson(): void
    {
        Notification::fake();
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Fam']);
        $learner = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Kid', 'current_level' => 1]);
        $lesson = $this->publishedLesson();
        $this->enroll($learner, $lesson->courseLevel->course);

        $coParent = $this->userWithRole('parent');
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $coParent->id, 'relationship' => 'co-parent', 'is_account_owner' => false]);

        $this->postJson('/api/v1/course-practice/invitations', [
            'learner_id' => $learner->id,
            'recipient_user_id' => $coParent->id,
        ])->assertCreated()->assertJsonPath('data.sent', true);

        $invitation = CoursePracticeInvitation::firstOrFail();
        $this->assertSame($lesson->id, $invitation->lesson_id);
        $this->assertTrue($invitation->expires_at->between(now()->addHours(47), now()->addHours(49)));
        Notification::assertSentTo($coParent, CoursePracticeInvitationNotification::class);
    }

    public function test_school_staff_are_eligible_but_an_unrelated_user_is_rejected(): void
    {
        Notification::fake();
        $this->seedRbac();
        $organization = Organization::create(['name' => 'School', 'type' => 'school', 'slug' => 'school', 'status' => 'active']);
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Fam']);
        $learner = LearnerProfile::create([
            'family_id' => $family->id, 'organization_id' => $organization->id, 'display_name' => 'Kid', 'current_level' => 1,
        ]);
        $lesson = $this->publishedLesson();
        $this->enroll($learner, $lesson->courseLevel->course);

        $teacher = $this->userWithRole('teacher');
        OrganizationUser::create(['organization_id' => $organization->id, 'user_id' => $teacher->id, 'role' => 'teacher', 'status' => 'active']);

        $this->postJson('/api/v1/course-practice/invitations', [
            'learner_id' => $learner->id,
            'recipient_user_id' => $teacher->id,
        ])->assertCreated();
        Notification::assertSentTo($teacher, CoursePracticeInvitationNotification::class);

        $stranger = $this->userWithRole('parent');
        $this->postJson('/api/v1/course-practice/invitations', [
            'learner_id' => $learner->id,
            'recipient_user_id' => $stranger->id,
        ])->assertStatus(422);
    }

    public function test_only_the_registered_recipient_can_open_and_accept_the_invitation(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $recipient = $this->userWithRole('parent');
        $stranger = $this->userWithRole('parent');
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Fam']);
        $learner = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Kid', 'current_level' => 1]);
        $lesson = $this->publishedLesson();
        $token = 'known-private-token';
        CoursePracticeInvitation::create([
            'learner_profile_id' => $learner->id,
            'lesson_id' => $lesson->id,
            'inviter_user_id' => $parent->id,
            'recipient_user_id' => $recipient->id,
            'token_hash' => hash('sha256', $token),
            'channel' => 'email',
            'expires_at' => now()->addHours(48),
        ]);

        $this->actingAsUser($stranger);
        $this->getJson("/api/v1/course-practice/invitations/{$token}")->assertForbidden();

        $this->actingAsUser($recipient);
        $this->getJson("/api/v1/course-practice/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.lesson_title', $lesson->title)
            ->assertJsonMissingPath('data.learner_id');

        $this->postJson("/api/v1/course-practice/invitations/{$token}/accept")
            ->assertOk()
            ->assertJsonPath('data.accepted', true);
    }
}
