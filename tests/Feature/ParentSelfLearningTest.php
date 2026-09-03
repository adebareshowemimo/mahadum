<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class ParentSelfLearningTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_parent_can_create_a_personal_learner_profile_and_start_a_course(): void
    {
        $this->seedRbac();
        $lesson = $this->publishedLesson();
        $course = $lesson->courseLevel->course;
        $parent = $this->actingAsUser($this->userWithRole('parent', [
            'first_name' => 'Ada',
            'last_name' => 'Obi',
            'date_of_birth' => '1990-05-10',
        ]));

        $learnerId = $this->postJson('/api/v1/me/learner-profile')
            ->assertCreated()
            ->assertJsonPath('data.display_name', 'Ada Obi')
            ->assertJsonPath('data.is_child', false)
            ->json('data.id');

        $this->postJson('/api/v1/enrollments', [
            'learner_id' => $learnerId,
            'course_id' => $course->id,
        ])->assertCreated()->assertJsonPath('data.course_id', $course->id);

        $this->assertDatabaseHas('learner_profiles', [
            'id' => $learnerId,
            'user_id' => $parent->id,
            'family_id' => null,
            'age_band' => 'adult',
        ]);
        $this->assertDatabaseHas('enrollments', ['learner_profile_id' => $learnerId, 'course_id' => $course->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'learner.self_profile_created', 'subject_id' => $learnerId]);
    }

    public function test_parent_self_profile_setup_is_idempotent_and_returned_by_me(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));

        $firstId = $this->postJson('/api/v1/me/learner-profile')->assertCreated()->json('data.id');
        $this->postJson('/api/v1/me/learner-profile')->assertOk()->assertJsonPath('data.id', $firstId);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonCount(1, 'data.learner_profiles')
            ->assertJsonPath('data.learner_profiles.0.id', $firstId);
        $this->assertDatabaseCount('learner_profiles', 1);
    }

    public function test_non_learning_staff_account_cannot_create_a_personal_learner_profile(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('supervisor'));

        $this->postJson('/api/v1/me/learner-profile')->assertForbidden();
    }
}
