<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\Organization;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class TeacherProfileTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_teacher_edits_details_and_school_sees_only_its_teacher(): void
    {
        $this->seedRbac();
        $school = Organization::create(['name' => 'School', 'slug' => 'teacher-profile', 'type' => 'school', 'status' => 'active']);
        $teacher = $this->userWithRole('teacher');
        $school->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $admin->update(['status' => 'active']);
        $school->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $this->actingAsUser($teacher);
        $this->putJson('/api/v1/teacher/profile', ['years_experience' => 7, 'native_language' => 'Yoruba',
            'state' => 'Lagos', 'dialects' => ['Egba', 'Oyo']])
            ->assertOk()->assertJsonPath('data.years_experience', 7);
        $this->getJson('/api/v1/teacher/profile')->assertOk()->assertJsonPath('data.dialects.0', 'Egba');
        $this->actingAsUser($admin);
        $this->getJson('/api/v1/teacher/profile')->assertForbidden();
        $this->getJson("/api/v1/schools/{$school->id}/teacher-directory")
            ->assertOk()->assertJsonPath('data.teachers.0.profile.state', 'Lagos');
    }

    public function test_parent_sees_only_the_teacher_of_their_school_enrolled_child(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $child = $this->parentWithChild($parent);
        $school = Organization::create(['name' => 'School', 'slug' => 'parent-teacher', 'type' => 'school', 'status' => 'active']);
        $teacher = $this->userWithRole('teacher');
        $school->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $class = SchoolClass::create(['organization_id' => $school->id, 'teacher_user_id' => $teacher->id, 'name' => 'A']);
        $child->update(['organization_id' => $school->id]);
        ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $child->id]);
        $this->actingAsUser($teacher);
        $this->putJson('/api/v1/teacher/profile', ['years_experience' => 9, 'native_language' => 'Igbo', 'dialects' => ['Onitsha']])->assertOk();
        $this->actingAsUser($parent);
        $this->getJson("/api/v1/family/children/{$child->id}")
            ->assertOk()->assertJsonCount(1, 'data.teachers')
            ->assertJsonPath('data.teachers.0.native_language', 'Igbo');
    }
}
