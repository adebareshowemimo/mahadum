<?php

namespace Tests\Feature;

use App\Models\XpLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class QuizAttemptCapTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_replay_past_max_attempts_is_practice_only(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();

        $quizC = $lesson->components->firstWhere('type', 'quiz');
        $quizC->quiz->update(['max_attempts' => 1]);
        $question = $quizC->quiz->questions->first();
        $correct = $question->options->firstWhere('is_correct', true);

        $answer = fn () => $this->postJson("/api/v1/components/{$quizC->id}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question->id, 'answer' => ['option_id' => $correct->id],
        ]);

        // First attempt: scored, XP awarded, attempt completed (1 question).
        $answer()->assertOk()
            ->assertJsonPath('data.correct', true)
            ->assertJsonPath('data.xp_awarded', 2)
            ->assertJsonPath('data.attempts_exhausted', false);
        $this->assertDatabaseCount('quiz_attempts', 1);

        // Replay past the cap: still graded (learning never blocked) but not scored.
        $answer()->assertOk()
            ->assertJsonPath('data.correct', true)
            ->assertJsonPath('data.xp_awarded', 0)
            ->assertJsonPath('data.attempts_exhausted', true);

        // No new attempt, no second XP row.
        $this->assertDatabaseCount('quiz_attempts', 1);
        $this->assertSame(1, XpLedger::where('source', 'quiz')->count());
    }

    public function test_unlimited_attempts_start_a_new_attempt_but_never_re_farm_xp(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson(); // max_attempts defaults to null (unlimited)

        $quizC = $lesson->components->firstWhere('type', 'quiz');
        $question = $quizC->quiz->questions->first();
        $correct = $question->options->firstWhere('is_correct', true);

        $answer = fn () => $this->postJson("/api/v1/components/{$quizC->id}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question->id, 'answer' => ['option_id' => $correct->id],
        ]);

        $answer()->assertOk()->assertJsonPath('data.xp_awarded', 2)->assertJsonPath('data.attempts_exhausted', false);
        // A replay starts a second scored attempt, but XP for the question isn't re-earned.
        $answer()->assertOk()->assertJsonPath('data.xp_awarded', 0)->assertJsonPath('data.attempts_exhausted', false);

        $this->assertDatabaseCount('quiz_attempts', 2);
        $this->assertSame(1, XpLedger::where('source', 'quiz')->count());
    }
}
