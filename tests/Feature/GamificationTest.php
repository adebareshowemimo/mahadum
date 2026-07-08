<?php

namespace Tests\Feature;

use App\Models\Course;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_completing_a_lesson_bumps_streak_and_awards_badges(): void
    {
        $this->seedRbac();
        $this->seed(BadgeSeeder::class);

        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $courseId = Course::first()->id;

        $quizC = $lesson->components->firstWhere('type', 'quiz');
        $videoC = $lesson->components->firstWhere('type', 'video');
        $speakC = $lesson->components->firstWhere('type', 'speaking');
        $question = $quizC->quiz->questions->first();
        $correct = $question->options->firstWhere('is_correct', true);

        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $courseId])->assertCreated();
        $this->postJson("/api/v1/components/{$quizC->id}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question->id, 'answer' => ['option_id' => $correct->id],
        ])->assertOk();
        $this->postJson("/api/v1/lessons/{$lesson->id}/progress", [
            'learner_id' => $learner->id, 'component_id' => $videoC->id, 'completed' => true,
        ])->assertOk();
        $this->postJson('/api/v1/speaking-submissions', [
            'learner_id' => $learner->id, 'component_id' => $speakC->id,
        ])->assertCreated();

        $complete = $this->postJson("/api/v1/lessons/{$lesson->id}/complete", ['learner_id' => $learner->id])->assertOk();
        $complete->assertJsonPath('data.streak.count', 1);

        $codes = collect($complete->json('data.badges_unlocked'))->pluck('code');
        $this->assertTrue($codes->contains('first_lesson'));
        $this->assertTrue($codes->contains('sharp_shooter'));

        $this->getJson("/api/v1/learners/{$learner->id}/streak")->assertOk()->assertJsonPath('data.count', 1);
        $this->getJson("/api/v1/learners/{$learner->id}/badges")->assertOk()
            ->assertJsonFragment(['code' => 'first_lesson']);
    }

    public function test_hearts_never_block_and_refill(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);

        $this->getJson("/api/v1/hearts?learner_id={$learner->id}")->assertOk()->assertJsonPath('data.current', 5);
        $this->postJson('/api/v1/hearts/refill', ['learner_id' => $learner->id, 'method' => 'coins'])
            ->assertOk()->assertJsonPath('data.current', 5);
    }
}
