<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class LearningLoopTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_enroll_play_answer_complete_with_score_and_xp(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $courseId = Course::first()->id;

        $quizC = $lesson->components->firstWhere('type', 'quiz');
        $videoC = $lesson->components->firstWhere('type', 'video');
        $speakC = $lesson->components->firstWhere('type', 'speaking');
        $question = $quizC->quiz->questions->first();
        $correct = $question->options->firstWhere('is_correct', true);

        // enroll → active first node
        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $courseId])
            ->assertCreated()->assertJsonPath('data.path.0.state', 'active');

        // answer correct → graded server-side, xp awarded
        $this->postJson("/api/v1/components/{$quizC->id}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question->id, 'answer' => ['option_id' => $correct->id],
        ])->assertOk()->assertJsonPath('data.correct', true)->assertJsonPath('data.xp_awarded', 2);

        // complete the other required components
        $this->postJson("/api/v1/lessons/{$lesson->id}/progress", [
            'learner_id' => $learner->id, 'component_id' => $videoC->id, 'completed' => true,
        ])->assertOk();
        $this->postJson('/api/v1/speaking-submissions', [
            'learner_id' => $learner->id, 'component_id' => $speakC->id,
        ])->assertCreated();

        // complete lesson → full score, xp 23, next_node null
        $this->postJson("/api/v1/lessons/{$lesson->id}/complete", ['learner_id' => $learner->id])
            ->assertOk()
            ->assertJsonPath('data.lesson_score', 1)
            ->assertJsonPath('data.xp_total', 23)
            ->assertJsonPath('data.next_node', null);

        $this->assertDatabaseHas('xp_ledger', ['learner_profile_id' => $learner->id, 'source' => 'quiz', 'amount' => 2]);
        $this->assertDatabaseHas('xp_ledger', ['learner_profile_id' => $learner->id, 'source' => 'lesson', 'amount' => 23]);
        $this->assertDatabaseHas('lesson_progress', ['learner_profile_id' => $learner->id, 'status' => 'completed']);
    }

    public function test_wrong_answer_is_marked_incorrect(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $quizC = $lesson->components->firstWhere('type', 'quiz');
        $question = $quizC->quiz->questions->first();
        $wrong = $question->options->firstWhere('is_correct', false);

        $this->postJson("/api/v1/components/{$quizC->id}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question->id, 'answer' => ['option_id' => $wrong->id],
        ])->assertOk()->assertJsonPath('data.correct', false)->assertJsonPath('data.xp_awarded', 0);
    }

    public function test_cannot_complete_with_incomplete_components(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();

        $this->postJson("/api/v1/lessons/{$lesson->id}/complete", ['learner_id' => $learner->id])
            ->assertStatus(422)->assertJsonPath('error.code', 'lesson_incomplete');
    }
}
