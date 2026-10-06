<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassTeacherAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $slug = 'assignment-school'): Organization
    {
        return Organization::create(['name' => 'Assignment School', 'type' => 'school', 'slug' => $slug, 'status' => 'active']);
    }

    private function member(Organization $school, string $role, string $status = 'active'): User
    {
        $user = $this->userWithRole($role);
        $school->members()->attach($user->id, ['role' => $role, 'status' => $status]);

        return $user;
    }

    private function classroom(Organization $school, ?User $teacher = null): SchoolClass
    {
        return SchoolClass::create([
            'organization_id' => $school->id,
            'name' => 'Existing JSS1',
            'level' => 'Level 1',
            'teacher_user_id' => $teacher?->id,
        ]);
    }

    public function test_admin_assigns_teacher_with_dashboard_payload_and_preserves_class_and_roster(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $admin = $this->member($school, 'school_admin');
        $teacher = $this->member($school, 'teacher');
        $class = $this->classroom($school);
        $learner = LearnerProfile::create(['organization_id' => $school->id, 'display_name' => 'Existing learner']);
        $enrollment = ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $learner->id]);
        $this->actingAsUser($admin);

        $this->putJson("/api/v1/classes/{$class->id}", ['teacher_user_id' => $teacher->id])
            ->assertOk()->assertJsonPath('data.name', 'Existing JSS1');

        $this->assertDatabaseHas('school_classes', [
            'id' => $class->id, 'organization_id' => $school->id,
            'name' => 'Existing JSS1', 'level' => 'Level 1', 'teacher_user_id' => $teacher->id,
        ]);
        $this->assertDatabaseHas('class_enrollments', ['id' => $enrollment->id, 'school_class_id' => $class->id, 'learner_profile_id' => $learner->id]);

        $this->actingAsUser($teacher);
        $this->getJson('/api/v1/classes?mine=1')->assertOk()->assertJsonPath('data.0.id', $class->id);
        $this->postJson("/api/v1/classes/{$class->id}/assignments", ['title' => 'Local regression assignment'])
            ->assertCreated();
    }

    public function test_partial_patch_preserves_omitted_name_and_teacher(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $admin = $this->member($school, 'school_admin');
        $teacher = $this->member($school, 'teacher');
        $class = $this->classroom($school, $teacher);
        $this->actingAsUser($admin);

        $this->patchJson("/api/v1/classes/{$class->id}", ['level' => 'Level 2'])->assertOk();
        $this->assertDatabaseHas('school_classes', [
            'id' => $class->id, 'name' => 'Existing JSS1', 'level' => 'Level 2', 'teacher_user_id' => $teacher->id,
        ]);
    }

    public function test_create_still_requires_a_name(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->actingAsUser($this->member($school, 'school_admin'));
        $teacher = $this->member($school, 'teacher');

        $this->postJson('/api/v1/classes', ['teacher_user_id' => $teacher->id])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('school_classes', 0);
    }

    public function test_explicit_invalid_names_are_rejected_without_changing_the_class(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $this->actingAsUser($this->member($school, 'school_admin'));
        $teacher = $this->member($school, 'teacher');
        $class = $this->classroom($school);

        foreach ([null, '', str_repeat('x', 256)] as $name) {
            $this->putJson("/api/v1/classes/{$class->id}", ['name' => $name, 'teacher_user_id' => $teacher->id])
                ->assertUnprocessable()->assertJsonValidationErrors('name');
        }
        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'name' => 'Existing JSS1', 'teacher_user_id' => null]);
    }

    public function test_assignment_requires_an_active_teacher_membership_in_this_school(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $otherSchool = $this->school('other-school');
        $this->actingAsUser($this->member($school, 'school_admin'));
        $class = $this->classroom($school);
        $invalidTeachers = [
            $this->member($school, 'teacher', 'inactive'),
            $this->member($otherSchool, 'teacher'),
            $this->member($school, 'school_admin'),
            $this->userWithRole('teacher'),
        ];

        foreach ($invalidTeachers as $teacher) {
            $this->putJson("/api/v1/classes/{$class->id}", ['teacher_user_id' => $teacher->id])
                ->assertUnprocessable()->assertJsonValidationErrors('teacher_user_id');
        }
        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'name' => 'Existing JSS1', 'teacher_user_id' => null]);
    }

    public function test_class_teacher_cannot_reassign_their_class_to_another_teacher(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $teacher = $this->member($school, 'teacher');
        $other = $this->member($school, 'teacher');
        $class = $this->classroom($school, $teacher);
        $this->actingAsUser($teacher);

        $this->putJson("/api/v1/classes/{$class->id}", ['teacher_user_id' => $other->id])->assertOk();
        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'name' => 'Existing JSS1', 'teacher_user_id' => $teacher->id]);
    }

    public function test_teacher_cannot_update_another_teachers_class(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $teacher = $this->member($school, 'teacher');
        $other = $this->member($school, 'teacher');
        $class = $this->classroom($school, $other);
        $this->actingAsUser($teacher);

        $this->putJson("/api/v1/classes/{$class->id}", ['teacher_user_id' => $teacher->id])->assertForbidden();
        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'teacher_user_id' => $other->id]);
    }

    public function test_school_admin_cannot_update_another_tenants_class(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $otherSchool = $this->school('other-school');
        $admin = $this->member($school, 'school_admin');
        $teacher = $this->member($school, 'teacher');
        $class = $this->classroom($otherSchool);
        $this->actingAsUser($admin);

        $this->putJson("/api/v1/classes/{$class->id}", ['teacher_user_id' => $teacher->id])->assertForbidden();
        $this->assertNull(SchoolClass::withoutTenancy()->findOrFail($class->id)->teacher_user_id);
    }

    public function test_unauthenticated_and_parent_accounts_cannot_assign_a_teacher(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $teacher = $this->member($school, 'teacher');
        $class = $this->classroom($school);
        $this->putJson("/api/v1/classes/{$class->id}", ['teacher_user_id' => $teacher->id])->assertUnauthorized();

        $this->actingAsUser($this->member($school, 'parent'));
        $this->putJson("/api/v1/classes/{$class->id}", ['teacher_user_id' => $teacher->id])->assertForbidden();
        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'teacher_user_id' => null]);
    }
}
