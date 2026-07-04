<?php

namespace Tests\Feature;

use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_requires_video_and_quiz(): void
    {
        // v1 rule: a lesson publishes with ≥1 video + ≥1 quiz. Speaking (which
        // needs learner recording + review) is deferred to v2 and not required.
        $this->seedRbac();
        $owner = $this->actingAsUser($this->userWithRole('content_owner'));
        $lang = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);

        $course = $this->postJson('/api/v1/courses', ['language_id' => $lang->id, 'title' => 'C'])
            ->assertCreated()->json('data.id');
        $level = $this->postJson("/api/v1/courses/$course/levels", ['title' => 'L1'])->assertCreated()->json('data.id');
        $lesson = $this->postJson("/api/v1/levels/$level/lessons", ['title' => 'Lesson'])->assertCreated()->json('data.id');

        // No components yet → publish fails with itemized reasons.
        $this->postJson("/api/v1/lessons/$lesson/publish")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'publish_checks_failed');

        $this->postJson("/api/v1/lessons/$lesson/components", [
            'type' => 'video', 'video' => ['title' => 'V', 'status' => 'ready'],
        ])->assertCreated();

        // Video alone is not enough — a quiz is still required.
        $this->postJson("/api/v1/lessons/$lesson/publish")->assertStatus(422);

        $this->postJson("/api/v1/lessons/$lesson/components", [
            'type' => 'quiz', 'quiz' => ['questions' => [
                ['type' => 'mcq_single', 'prompt' => 'Q', 'options' => [
                    ['label' => 'A', 'is_correct' => true], ['label' => 'B', 'is_correct' => false],
                ]],
            ]],
        ])->assertCreated();

        // Video + quiz is publishable without a speaking step.
        $this->postJson("/api/v1/lessons/$lesson/publish")->assertOk()->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('lessons', ['id' => $lesson]);
        $this->assertNotNull($this->getJson("/api/v1/lessons/$lesson")->json('data.published_at'));
    }

    public function test_play_payload_strips_correct_answers(): void
    {
        $this->seedRbac();
        $owner = $this->actingAsUser($this->userWithRole('content_owner'));

        // Build a lesson with a quiz directly through the trait-free path:
        $lang = Language::create(['code' => 'yo', 'name' => 'Yoruba', 'script' => 'latin', 'is_active' => true]);
        $course = $this->postJson('/api/v1/courses', ['language_id' => $lang->id, 'title' => 'C'])->json('data.id');
        $level = $this->postJson("/api/v1/courses/$course/levels", ['title' => 'L1'])->json('data.id');
        $lesson = $this->postJson("/api/v1/levels/$level/lessons", ['title' => 'L'])->json('data.id');
        $this->postJson("/api/v1/lessons/$lesson/components", [
            'type' => 'quiz', 'quiz' => ['questions' => [
                ['type' => 'mcq_single', 'prompt' => 'Q', 'options' => [['label' => 'A', 'is_correct' => true]]],
            ]],
        ])->assertCreated();

        $play = $this->getJson("/api/v1/lessons/$lesson/play")->assertOk()->json('data');
        $options = $play['components'][0]['quiz']['questions'][0]['options'];

        $this->assertArrayNotHasKey('is_correct', $options[0]);
    }
}
