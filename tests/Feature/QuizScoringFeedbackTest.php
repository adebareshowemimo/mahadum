<?php

namespace Tests\Feature;

use App\Models\QuizAttempt;
use App\Models\XpLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class QuizScoringFeedbackTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_nine_correct_answers_earn_nine_xp_and_resume_preserves_results(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $component = $lesson->components->firstWhere('type', 'quiz');
        $quiz = $component->quiz;
        foreach (range(2, 10) as $position) {
            $question = $quiz->questions()->create(['position' => $position, 'type' => 'mcq_single', 'prompt' => 'Pick', 'points' => 8]);
            $question->options()->create(['label' => 'Right', 'is_correct' => true, 'position' => 1]);
            $question->options()->create(['label' => 'Wrong', 'is_correct' => false, 'position' => 2]);
        }
        $questions = $quiz->questions()->with('options')->orderBy('position')->get();
        foreach ($questions as $index => $question) {
            $payload = ['learner_id' => $learner->id, 'question_id' => $question->id,
                'answer' => ['option_id' => $question->options->firstWhere('is_correct', $index < 9)->id]];
            $this->postJson("/api/v1/components/{$component->id}/answer", $payload)->assertOk()
                ->assertJsonPath('data.xp_awarded', $index < 9 ? 1 : 0);
            if ($index === 0) {
                // Network retry within the open attempt must not double-count hearts or XP.
                $this->postJson("/api/v1/components/{$component->id}/answer", $payload)->assertOk()->assertJsonPath('data.xp_awarded', 0);
                $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk()
                    ->assertJsonPath('data.components.1.quiz.questions.0.xp_awarded', 1)
                    ->assertJsonPath('data.components.1.quiz.questions.0.was_correct', true);
            }
        }
        $this->assertSame(9, (int) XpLedger::where('learner_profile_id', $learner->id)->where('source', 'quiz')->sum('amount'));
        $attempt = QuizAttempt::firstOrFail();
        $this->assertEquals(0.9, $attempt->score);
        $this->assertTrue($attempt->passed);
        $this->assertNotNull($attempt->completed_at);
        $this->getJson("/api/v1/hearts?learner_id={$learner->id}")->assertOk()->assertJsonPath('data.current', 3);
    }
}
