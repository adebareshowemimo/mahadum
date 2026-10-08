<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\SchoolClass;
use App\Models\TeacherInvitation;
use App\Models\User;
use App\Notifications\TeacherInvited;
use App\Services\UserAccountType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class Batch4OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function school(): array
    {
        $this->seedRbac();
        $org = Organization::create(['name' => 'School', 'type' => 'school', 'slug' => 'batch4-school', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);

        return [$org, $admin->fresh()];
    }

    private function invitation(Organization $org, User $issuer, User $recipient): string
    {
        $token = str_repeat('a', 64);
        TeacherInvitation::create(['organization_id' => $org->id, 'invited_by_user_id' => $issuer->id, 'name' => $recipient->name,
            'email' => $recipient->email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);

        return $token;
    }

    public function test_school_admin_invites_by_email_without_granting_authority_and_resend_revokes_old_link(): void
    {
        [$org, $admin] = $this->school();
        Notification::fake();
        $this->actingAsUser($admin);
        foreach (range(1, 2) as $unused) {
            $this->postJson("/api/v1/schools/$org->id/teacher-invitations", ['name' => 'New Teacher', 'email' => 'NEW@example.com'])
                ->assertCreated()->assertJsonPath('data.email', 'new@example.com')->assertJsonPath('data.delivery_status', 'not_configured')->assertJsonMissingPath('data.token');
        }
        $this->assertSame(1, TeacherInvitation::whereNull('revoked_at')->count());
        $this->assertDatabaseCount('organization_user', 1);
        Notification::assertSentOnDemandTimes(TeacherInvited::class, 2);
        $this->getJson("/api/v1/schools/$org->id/teacher-directory")->assertOk()->assertJsonCount(1, 'data.invitations')->assertJsonCount(0, 'data.teachers');
    }

    public function test_teacher_cannot_invite_teachers_and_foreign_admin_cannot_read_directory(): void
    {
        [$org] = $this->school();
        $teacher = $this->userWithRole('teacher')->fresh();
        $org->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $this->actingAsUser($teacher);
        $this->postJson("/api/v1/schools/$org->id/teacher-invitations", ['name' => 'Test', 'email' => 'test@example.com'])->assertForbidden();
        $this->actingAsUser($this->userWithRole('school_admin')->fresh());
        $this->getJson("/api/v1/schools/$org->id/students")->assertForbidden();
    }

    public function test_student_directory_excludes_foreign_students_and_inconsistent_foreign_enrollments(): void
    {
        [$org, $admin] = $this->school();
        $other = Organization::create(['name' => 'Other', 'type' => 'school', 'slug' => 'other', 'status' => 'active']);
        $local = LearnerProfile::create(['organization_id' => $org->id, 'display_name' => 'Local learner']);
        LearnerProfile::create(['organization_id' => $other->id, 'display_name' => 'Foreign learner']);
        $ownClass = SchoolClass::create(['organization_id' => $org->id, 'name' => 'Own class']);
        $foreignClass = SchoolClass::create(['organization_id' => $other->id, 'name' => 'Foreign class']);
        $local->classEnrollments()->create(['school_class_id' => $ownClass->id]);
        $local->classEnrollments()->create(['school_class_id' => $foreignClass->id]);
        $this->actingAsUser($admin);
        $this->getJson("/api/v1/schools/$org->id/students")->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(1, 'data.0.classes')
            ->assertJsonPath('data.0.classes.0.name', 'Own class')->assertJsonMissing(['name' => 'Foreign learner'])->assertJsonMissing(['name' => 'Foreign class']);
        $admin->update(['status' => 'suspended']);
        $this->getJson("/api/v1/schools/$org->id/teacher-directory")->assertForbidden();
    }

    public function test_acceptance_preserves_parent_family_and_is_replay_safe_without_reactivating_suspended_membership(): void
    {
        [$org, $admin] = $this->school();
        $parent = $this->userWithRole('parent')->fresh();
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Existing family']);
        $child = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Child']);
        $token = $this->invitation($org, $admin, $parent);
        $this->actingAsUser($parent);
        $this->postJson("/api/v1/teacher-invitations/$token/accept")->assertOk()->assertJsonPath('data.organization_id', $org->id);
        $this->postJson("/api/v1/teacher-invitations/$token/accept")->assertOk();
        $this->assertTrue($parent->fresh()->hasAllRoles(['parent', 'teacher']));
        $this->assertSame($family->id, $child->fresh()->family_id);
        $this->assertDatabaseCount('organization_user', 2);
        OrganizationUser::where('user_id', $parent->id)->update(['status' => 'suspended']);
        $this->postJson("/api/v1/teacher-invitations/$token/accept")->assertOk();
        $this->assertSame('suspended', OrganizationUser::where('user_id', $parent->id)->sole()->status);
    }

    public function test_invite_rejects_wrong_email_unverified_user_expiry_and_revocation(): void
    {
        [$org, $admin] = $this->school();
        $recipient = User::factory()->create(['email_verified_at' => null])->fresh();
        $token = $this->invitation($org, $admin, $recipient);
        $this->actingAsUser(User::factory()->create());
        $this->postJson("/api/v1/teacher-invitations/$token/accept")->assertForbidden();
        $this->actingAsUser($recipient);
        $this->postJson("/api/v1/teacher-invitations/$token/accept")->assertForbidden();
        $recipient->update(['email_verified_at' => now()]);
        TeacherInvitation::query()->update(['expires_at' => now()->subSecond()]);
        $this->postJson("/api/v1/teacher-invitations/$token/accept")->assertGone();
        TeacherInvitation::query()->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
        $this->postJson("/api/v1/teacher-invitations/$token/accept")->assertGone();
        $this->assertFalse($recipient->fresh()->hasRole('teacher'));
    }

    public function test_inactive_issuer_or_existing_non_teacher_membership_requires_school_review(): void
    {
        [$org, $admin] = $this->school();
        $recipient = User::factory()->create()->fresh();
        $token = $this->invitation($org, $admin, $recipient);
        $this->actingAsUser($recipient);
        $admin->update(['status' => 'suspended']);
        $this->postJson("/api/v1/teacher-invitations/$token/accept")->assertForbidden();
        $admin->update(['status' => 'active']);
        $org->members()->attach($recipient->id, ['role' => 'student', 'status' => 'active']);
        $this->postJson("/api/v1/teacher-invitations/$token/accept")->assertConflict();
        $this->assertSame('student', OrganizationUser::where('user_id', $recipient->id)->sole()->role);
        OrganizationUser::where('user_id', $recipient->id)->update(['role' => 'school_admin']);
        $this->postJson("/api/v1/teacher-invitations/$token/accept")->assertConflict();
        $this->assertFalse($recipient->fresh()->hasRole('teacher'));
    }

    public function test_teacher_signup_creates_no_school_family_or_teaching_grant(): void
    {
        $this->seedRbac();
        Notification::fake();
        $this->postJson('/api/v1/auth/register', ['first_name' => 'New', 'last_name' => 'Teacher', 'email' => 'signup@example.com',
            'phone' => '08031234567', 'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'account_type' => 'teacher', 'date_of_birth' => '1990-01-01', 'device_name' => 'test'])->assertCreated();
        $user = User::where('email', 'signup@example.com')->sole();
        $this->assertSame('teacher', $user->signup_account_type);
        $this->assertFalse($user->hasAnyRole(['teacher', 'school_admin', 'parent']));
        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('families', 0);
        $this->assertDatabaseCount('learner_profiles', 0);
    }

    public function test_institution_school_and_unlinked_teacher_account_filters_are_disjoint(): void
    {
        [$org, $admin] = $this->school();
        $institution = Organization::create(['name' => 'Institution', 'type' => 'institution', 'slug' => 'institution', 'status' => 'active']);
        $institutionUser = User::factory()->create();
        $institution->members()->attach($institutionUser->id, ['role' => 'school_admin', 'status' => 'active']);
        $org->members()->attach($institutionUser->id, ['role' => 'teacher', 'status' => 'active']);
        $unlinked = User::factory()->create(['signup_account_type' => 'teacher']);
        $this->assertSame([$institutionUser->id], UserAccountType::filter(User::query(), 'institution')->pluck('id')->all());
        $this->assertSame([$admin->id], UserAccountType::filter(User::query(), 'school')->pluck('id')->all());
        $this->assertSame([$unlinked->id], UserAccountType::filter(User::query(), 'teacher')->pluck('id')->all());
        $this->assertSame(0, UserAccountType::filter(User::query(), 'single')->count());
    }

    public function test_family_teacher_keeps_family_identity_across_home_admin_and_filters(): void
    {
        [$org, $admin] = $this->school();
        $parent = $this->userWithRole('parent');
        $parent->assignRole('teacher');
        Family::create(['owner_user_id' => $parent->id, 'name' => 'Family']);
        $org->members()->attach($parent->id, ['role' => 'teacher', 'status' => 'active']);
        $this->assertSame('family', UserAccountType::forUser($parent));
        $this->assertTrue(UserAccountType::filter(User::query(), 'family')->whereKey($parent->id)->exists());
        foreach (['teacher', 'school', 'institution', 'single'] as $type) {
            $this->assertFalse(UserAccountType::filter(User::query(), $type)->whereKey($parent->id)->exists());
        }
        $this->actingAsUser($parent);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.user.account_type', 'family');
        $this->actingAsUser($this->userWithRole('super_admin'));
        $this->getJson("/api/v1/admin/users/{$parent->id}")->assertOk()->assertJsonPath('data.account_type', 'family');
        $this->assertSame('school', UserAccountType::forUser($admin));
    }
}
