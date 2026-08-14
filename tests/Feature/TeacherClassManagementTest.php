<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\Enrollment;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class TeacherClassManagementTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function school(): Organization
    {
        return Organization::create([
            'name' => 'Teacher School',
            'type' => 'school',
            'slug' => 'teacher-school',
            'status' => 'active',
        ]);
    }

    public function test_teacher_creates_own_class_and_cannot_manage_another_teachers_class(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $teacher = $this->userWithRole('teacher');
        $other = $this->userWithRole('teacher');
        $school->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $school->members()->attach($other->id, ['role' => 'teacher', 'status' => 'active']);
        $this->actingAsUser($teacher);

        $classId = $this->postJson('/api/v1/classes', [
            'name' => 'My JSS1',
            'teacher_user_id' => $other->id,
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('school_classes', [
            'id' => $classId,
            'teacher_user_id' => $teacher->id,
        ]);

        $otherClass = SchoolClass::create([
            'organization_id' => $school->id,
            'name' => 'Not mine',
            'teacher_user_id' => $other->id,
        ]);

        $this->postJson("/api/v1/classes/{$otherClass->id}/learners", ['display_name' => 'Blocked'])
            ->assertForbidden();
    }

    public function test_teacher_without_an_active_school_membership_cannot_read_or_create_classes(): void
    {
        $this->seedRbac();
        $teacher = $this->userWithRole('teacher');
        $this->actingAsUser($teacher);

        $this->getJson('/api/v1/classes')->assertForbidden();
        $this->postJson('/api/v1/classes', ['name' => 'Orphaned class'])->assertForbidden();

        $this->assertDatabaseMissing('school_classes', ['name' => 'Orphaned class']);
    }

    public function test_teacher_assigns_course_to_current_and_future_class_learners(): void
    {
        $this->seedRbac();
        $school = $this->school();
        $teacher = $this->userWithRole('teacher');
        $school->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $class = SchoolClass::create([
            'organization_id' => $school->id,
            'name' => 'JSS1',
            'teacher_user_id' => $teacher->id,
        ]);
        $existing = LearnerProfile::create([
            'organization_id' => $school->id,
            'display_name' => 'Existing learner',
        ]);
        ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $existing->id]);
        $course = $this->publishedLesson()->courseLevel->course;
        $this->actingAsUser($teacher);

        $this->getJson("/api/v1/classes/{$class->id}/courses")
            ->assertOk()
            ->assertJsonPath('data.0.assigned', false);

        $this->postJson("/api/v1/classes/{$class->id}/courses/{$course->id}")
            ->assertCreated()
            ->assertJsonPath('data.enrolled_count', 1);

        $this->assertDatabaseHas('enrollments', [
            'learner_profile_id' => $existing->id,
            'course_id' => $course->id,
        ]);

        $course->update(['is_published' => false]);
        $this->getJson("/api/v1/classes/{$class->id}/courses")
            ->assertOk()
            ->assertJsonPath('data.0.id', $course->id)
            ->assertJsonPath('data.0.assigned', true)
            ->assertJsonPath('data.0.is_published', false);
        $course->update(['is_published' => true]);
        $existingEnrollmentId = Enrollment::where('learner_profile_id', $existing->id)
            ->where('course_id', $course->id)
            ->value('id');
        $this->assertDatabaseHas('learner_path_nodes', ['enrollment_id' => $existingEnrollmentId]);

        $rosterLearner = LearnerProfile::create([
            'organization_id' => $school->id,
            'display_name' => 'Existing roster learner',
        ]);
        $this->getJson("/api/v1/classes/{$class->id}/available-learners")
            ->assertOk()
            ->assertJsonPath('data.0.id', $rosterLearner->id);
        $this->postJson("/api/v1/classes/{$class->id}/learners", [
            'learner_id' => $rosterLearner->id,
        ])->assertCreated()->assertJsonPath('data.courses_enrolled', 1);
        $this->assertDatabaseHas('enrollments', [
            'learner_profile_id' => $rosterLearner->id,
            'course_id' => $course->id,
        ]);

        $future = LearnerProfile::create([
            'organization_id' => $school->id,
            'display_name' => 'Future learner',
            'age_band' => 'JSS1',
        ]);
        $futureId = $this->postJson("/api/v1/classes/{$class->id}/learners", [
            'learner_id' => $future->id,
        ])->assertCreated()
            ->assertJsonPath('data.courses_enrolled', 1)
            ->json('data.learner_id');

        $this->assertDatabaseHas('class_enrollments', [
            'school_class_id' => $class->id,
            'learner_profile_id' => $futureId,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'learner_profile_id' => $futureId,
            'course_id' => $course->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'class.course_assigned']);
    }
}
