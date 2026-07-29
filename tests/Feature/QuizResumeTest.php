<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class QuizResumeTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_play_payload_flags_answered_questions_for_quiz_resume(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $courseId = Course::first()->id;

        // Give the quiz a second question so answering the first leaves the
        // attempt OPEN (a half-finished quiz that can be resumed).
        $quizComponent = $lesson->components->firstWhere('type', 'quiz');
        $quiz = $quizComponent->quiz;
        $second = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Second', 'points' => 1, 'position' => 2]);
        $second->options()->create(['label' => 'A', 'is_correct' => true, 'position' => 1]);
        $second->options()->create(['label' => 'B', 'is_correct' => false, 'position' => 2]);

        $first = $quiz->questions()->where('position', 1)->firstOrFail();
        $firstCorrect = $first->options()->where('is_correct', true)->firstOrFail();

        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $courseId])->assertCreated();

        // Before answering: nothing is flagged.
        $before = $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk();
        $this->assertFalse($before->json('data.components.1.quiz.questions.0.answered'));
        $this->assertNull($before->json('data.components.1.quiz.questions.0.was_correct'));

        // Answer the first question correctly (attempt stays open — 1 of 2).
        $this->postJson("/api/v1/components/{$quizComponent->id}/answer", [
            'learner_id' => $learner->id,
            'question_id' => $first->id,
            'answer' => ['option_id' => $firstCorrect->id],
        ])->assertOk();

        // The payload now resumes: Q1 answered + correct, Q2 still open, quiz not complete.
        $after = $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk();
        $this->assertTrue($after->json('data.components.1.quiz.questions.0.answered'));
        $this->assertTrue($after->json('data.components.1.quiz.questions.0.was_correct'));
        $this->assertFalse($after->json('data.components.1.quiz.questions.1.answered'));
        $this->assertFalse($after->json('data.components.1.completed'));
    }

    public function test_answered_flags_are_absent_without_a_learner(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));
        $lesson = $this->publishedLesson();

        $payload = $this->getJson("/api/v1/lessons/{$lesson->id}/play")->assertOk();

        // No learner context → the question is reported as not answered.
        $this->assertFalse($payload->json('data.components.1.quiz.questions.0.answered'));
    }
}
