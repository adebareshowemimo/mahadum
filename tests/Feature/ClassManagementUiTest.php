<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class ClassManagementUiTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function school(string $slug = 'class-ui-school'): Organization
    {
        return Organization::create(['name' => 'Isolated UI school', 'type' => 'school', 'slug' => $slug, 'status' => 'active']);
    }

    public function test_class_detail_exposes_existing_admin_management_capabilities_and_assignment_ids(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $admin = $this->userWithRole('school_admin');
        $school->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => '%th grade', 'level' => 'L1']);
        $this->actingAsUser($admin);

        $this->getJson("/api/v1/classes/{$class->id}")->assertOk()
            ->assertJsonPath('data.organization_id', $school->id)
            ->assertJsonPath('data.teacher_user_id', null)
            ->assertJsonPath('data.capabilities.update', true)
            ->assertJsonPath('data.capabilities.assign_teacher', true);
    }

    public function test_mixed_parent_teacher_keeps_family_and_edits_only_own_class_without_reassignment(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $teacher = $this->userWithRole('parent');
        $learner = $this->parentWithChild($teacher);
        $teacher->assignRole('teacher');
        $school->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Own class', 'teacher_user_id' => $teacher->id]);
        $this->actingAsUser($teacher);

        $this->getJson('/api/v1/classes?mine=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $class->id);
        $this->getJson("/api/v1/classes/{$class->id}")->assertOk()
            ->assertJsonPath('data.capabilities.update', true)->assertJsonPath('data.capabilities.assign_teacher', false);
        $this->patchJson("/api/v1/classes/{$class->id}", ['name' => 'Updated own class', 'level' => 'L2'])->assertOk();
        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'teacher_user_id' => $teacher->id]);
        $this->assertDatabaseHas('families', ['id' => $learner->family_id, 'owner_user_id' => $teacher->id]);
        $this->assertDatabaseHas('learner_profiles', ['id' => $learner->id, 'family_id' => $learner->family_id]);
    }

    public function test_read_only_viewer_and_other_class_teacher_do_not_receive_edit_capabilities(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $owner = $this->userWithRole('teacher');
        $school->members()->attach($owner->id, ['role' => 'teacher', 'status' => 'active']);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Existing class', 'teacher_user_id' => $owner->id]);
        foreach (['teacher', 'supervisor'] as $role) {
            $viewer = $this->userWithRole($role);
            $school->members()->attach($viewer->id, ['role' => $role, 'status' => 'active']);
            $this->actingAsUser($viewer);
            $this->getJson("/api/v1/classes/{$class->id}")->assertOk()
                ->assertJsonPath('data.capabilities.update', false)->assertJsonPath('data.capabilities.assign_teacher', false);
            $this->patchJson("/api/v1/classes/{$class->id}", ['name' => 'Blocked edit'])->assertForbidden();
        }
    }

    public function test_role_alone_and_inactive_school_membership_do_not_grant_class_access(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $teacher = $this->userWithRole('parent');
        $this->parentWithChild($teacher);
        $teacher->assignRole('teacher');
        $this->actingAsUser($teacher);
        $this->getJson('/api/v1/classes?mine=1')->assertForbidden();
        $this->postJson('/api/v1/classes', ['name' => 'Blocked'])->assertForbidden();
        $school->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'inactive']);
        $this->getJson('/api/v1/classes?mine=1')->assertForbidden();
        $this->assertDatabaseCount('school_classes', 0);
        $this->assertDatabaseCount('families', 1);
    }
}
