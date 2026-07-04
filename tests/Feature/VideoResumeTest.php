<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class VideoResumeTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_play_payload_returns_saved_playhead_for_the_learner(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $courseId = Course::first()->id;
        $video = $lesson->components->firstWhere('type', 'video');

        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $courseId])->assertCreated();

        // Watch partway through, then pause.
        $this->postJson("/api/v1/lessons/{$lesson->id}/progress", [
            'learner_id' => $learner->id, 'component_id' => $video->id,
            'event' => 'played', 'play_delta' => 1, 'position_seconds' => 0, 'duration_seconds' => 120,
        ])->assertOk();
        $this->postJson("/api/v1/lessons/{$lesson->id}/progress", [
            'learner_id' => $learner->id, 'component_id' => $video->id,
            'event' => 'paused', 'watched_delta' => 42, 'position_seconds' => 42,
        ])->assertOk();

        // The play payload echoes the saved playhead for this learner.
        $withLearner = $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk();
        $this->assertEquals(42, $withLearner->json('data.components.0.resume_position'));
        $this->assertFalse($withLearner->json('data.components.0.completed'));

        // Without a learner, there's no resume context (defaults to start).
        $noLearner = $this->getJson("/api/v1/lessons/{$lesson->id}/play")->assertOk();
        $this->assertEquals(0, $noLearner->json('data.components.0.resume_position'));
    }

    public function test_completed_video_reports_completed_so_the_gate_starts_open(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $courseId = Course::first()->id;
        $video = $lesson->components->firstWhere('type', 'video');

        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $courseId])->assertCreated();
        $this->postJson("/api/v1/lessons/{$lesson->id}/progress", [
            'learner_id' => $learner->id, 'component_id' => $video->id,
            'event' => 'completed', 'watched_delta' => 60, 'position_seconds' => 60, 'duration_seconds' => 60, 'completed' => true,
        ])->assertOk();

        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")
            ->assertOk()
            ->assertJsonPath('data.components.0.completed', true);
    }
}
