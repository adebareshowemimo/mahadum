<?php

namespace Tests\Feature;

use App\Models\PlacementAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class PlacementPathTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_intermediate_placement_starts_at_matching_published_level_and_parent_override_wins(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $beginner = $this->publishedLesson();
        $course = $beginner->courseLevel->course;
        $intermediate = $course->levels()->create(['title' => 'Intermediate', 'position' => 2, 'is_free' => true])
            ->lessons()->create(['title' => 'Intermediate lesson', 'position' => 1, 'published_at' => now()]);
        PlacementAssessment::create(['learner_profile_id' => $learner->id, 'language_id' => $course->language_id,
            'result_level' => 'Intermediate', 'completed_at' => now()]);

        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $course->id])
            ->assertCreated()->assertJsonCount(1, 'data.path')->assertJsonPath('data.path.0.lesson_id', $intermediate->id);

        $another = $learner->family->learnerProfiles()->create(['display_name' => 'Another learner',
            'starting_level_position' => 0]);
        PlacementAssessment::create(['learner_profile_id' => $another->id, 'language_id' => $course->language_id,
            'result_level' => 'Intermediate', 'completed_at' => now()]);
        $this->postJson('/api/v1/enrollments', ['learner_id' => $another->id, 'course_id' => $course->id])
            ->assertCreated()->assertJsonCount(2, 'data.path')->assertJsonPath('data.path.0.lesson_id', $beginner->id);
    }
}
