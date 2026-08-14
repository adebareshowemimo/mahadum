<?php

namespace Tests\Feature;

use App\Models\ClassLearnerInvitation;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\ClassLearnerInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClassLearnerInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $slug = 'invitation-school'): Organization
    {
        return Organization::create([
            'name' => 'Invitation School',
            'type' => 'school',
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    public function test_teacher_sends_a_class_invitation_email_for_their_class(): void
    {
        Notification::fake();
        $this->seedRbac();
        $school = $this->school();
        $teacher = $this->userWithRole('teacher');
        $school->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $class = SchoolClass::create([
            'organization_id' => $school->id,
            'teacher_user_id' => $teacher->id,
            'name' => 'Yoruba A1',
        ]);
        $this->actingAsUser($teacher);

        $this->postJson("/api/v1/classes/{$class->id}/invitations", [
            'name' => 'Ada Learner',
            'email' => 'ADA@example.com',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'ada@example.com');

        $this->assertDatabaseHas('class_learner_invitations', [
            'school_class_id' => $class->id,
            'organization_id' => $school->id,
            'email' => 'ada@example.com',
        ]);
        Notification::assertSentOnDemand(ClassLearnerInvited::class);
    }

    public function test_available_learner_search_is_limited_to_the_teachers_school(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $otherSchool = $this->school('other-school');
        $teacher = $this->userWithRole('teacher');
        $school->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $class = SchoolClass::create([
            'organization_id' => $school->id,
            'teacher_user_id' => $teacher->id,
            'name' => 'Igbo A1',
        ]);
        $localUser = User::factory()->create(['email' => 'local@example.com']);
        $local = LearnerProfile::create([
            'user_id' => $localUser->id,
            'organization_id' => $school->id,
            'display_name' => 'Local Learner',
        ]);
        LearnerProfile::create([
            'organization_id' => $otherSchool->id,
            'display_name' => 'Local Learner Outside',
        ]);
        $this->actingAsUser($teacher);

        $this->getJson("/api/v1/classes/{$class->id}/available-learners?q=local")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $local->id)
            ->assertJsonPath('data.0.email', 'local@example.com');
    }

    public function test_existing_user_accepts_the_invitation_during_login(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $teacher = $this->userWithRole('teacher');
        $class = SchoolClass::create([
            'organization_id' => $school->id,
            'teacher_user_id' => $teacher->id,
            'name' => 'Hausa A1',
        ]);
        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'password' => 'Password123!',
        ]);
        $token = str_repeat('a', 64);
        ClassLearnerInvitation::create([
            'school_class_id' => $class->id,
            'organization_id' => $school->id,
            'invited_by_user_id' => $teacher->id,
            'name' => 'Invited Learner',
            'email' => $user->email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'Test browser',
            'class_invitation_token' => $token,
        ])->assertOk();

        $profile = LearnerProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($school->id, $profile->organization_id);
        $this->assertDatabaseHas('class_enrollments', [
            'school_class_id' => $class->id,
            'learner_profile_id' => $profile->id,
        ]);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $school->id,
            'user_id' => $user->id,
            'role' => 'student',
            'status' => 'active',
        ]);
    }

    public function test_new_user_registers_with_the_invited_email_and_joins_the_class(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $teacher = $this->userWithRole('teacher');
        $class = SchoolClass::create([
            'organization_id' => $school->id,
            'teacher_user_id' => $teacher->id,
            'name' => 'Pidgin A1',
        ]);
        $token = str_repeat('b', 64);
        ClassLearnerInvitation::create([
            'school_class_id' => $class->id,
            'organization_id' => $school->id,
            'invited_by_user_id' => $teacher->id,
            'name' => 'New Learner',
            'email' => 'new@example.com',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'New',
            'last_name' => 'Learner',
            'email' => 'new@example.com',
            'phone' => '+2348000000000',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'device_name' => 'Test browser',
            'account_type' => 'learner',
            'class_invitation_token' => $token,
        ])->assertCreated();

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $profile = LearnerProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($user->hasRole('student'));
        $this->assertSame($school->id, $profile->organization_id);
        $this->assertDatabaseHas('class_enrollments', [
            'school_class_id' => $class->id,
            'learner_profile_id' => $profile->id,
        ]);
    }
}
