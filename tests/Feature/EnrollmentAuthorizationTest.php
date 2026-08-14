<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LearnerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class EnrollmentAuthorizationTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_student_can_enroll_their_own_learner_profile(): void
    {
        $this->seedRbac();
        $student = $this->actingAsUser($this->userWithRole('student'));
        $learner = LearnerProfile::create(['user_id' => $student->id, 'display_name' => 'Self learner']);
        $course = $this->publishedLesson()->courseLevel->course;

        $this->postJson('/api/v1/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $course->id,
        ])->assertCreated()
            ->assertJsonPath('data.course_id', $course->id)
            ->assertJsonPath('data.path.0.state', 'active');
    }

    public function test_student_cannot_enroll_another_learner(): void
    {
        $this->seedRbac();
        $student = $this->actingAsUser($this->userWithRole('student'));
        LearnerProfile::create(['user_id' => $student->id, 'display_name' => 'Self learner']);
        $otherLearner = LearnerProfile::create(['display_name' => 'Another learner']);
        $course = $this->publishedLesson()->courseLevel->course;

        $this->postJson('/api/v1/enrollments', [
            'learner_id' => $otherLearner->id,
            'course_id' => $course->id,
        ])->assertForbidden();
    }

    public function test_parent_can_enroll_only_their_own_child(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $ownChild = $this->parentWithChild($parent);
        $otherParent = $this->userWithRole('parent');
        $otherChild = $this->parentWithChild($otherParent);
        $course = $this->publishedLesson()->courseLevel->course;

        $this->postJson('/api/v1/enrollments', [
            'learner_id' => $ownChild->id,
            'course_id' => $course->id,
        ])->assertCreated();

        $this->postJson('/api/v1/enrollments', [
            'learner_id' => $otherChild->id,
            'course_id' => $course->id,
        ])->assertForbidden();
    }

    public function test_staff_progress_access_does_not_allow_enrollment(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('supervisor'));
        $learner = LearnerProfile::create(['display_name' => 'Observed learner']);
        $course = Course::findOrFail($this->publishedLesson()->courseLevel->course_id);

        $this->postJson('/api/v1/enrollments', [
            'learner_id' => $learner->id,
            'course_id' => $course->id,
        ])->assertForbidden();
    }
}
