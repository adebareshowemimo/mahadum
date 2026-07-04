<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class LessonAnalyticsTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_lesson_analytics_reports_funnel_and_question_accuracy(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();

        $quizC = $lesson->components->firstWhere('type', 'quiz');
        $question = $quizC->quiz->questions->first();
        $correct = $question->options->firstWhere('is_correct', true);

        // A learner answers the quiz question → generates progress + a response.
        $this->postJson("/api/v1/components/{$quizC->id}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question->id, 'answer' => ['option_id' => $correct->id],
        ])->assertOk();

        // Content owner reads the analytics.
        $this->actingAsUser($this->userWithRole('content_owner'));
        $data = $this->getJson("/api/v1/lessons/{$lesson->id}/analytics")->assertOk()->json('data');

        $this->assertSame(1, $data['learners_started']);
        $this->assertSame(0, $data['learners_completed']);

        $quizFunnel = collect($data['funnel'])->firstWhere('type', 'quiz');
        $this->assertSame(1, $quizFunnel['reached']);
        $this->assertSame(1, $quizFunnel['completed']);

        $q = collect($data['questions'])->firstWhere('question_id', $question->id);
        $this->assertSame(1, $q['answered']);
        $this->assertSame(1, $q['correct']);
        $this->assertEquals(1, $q['accuracy']);
    }

    public function test_lesson_analytics_requires_the_permission(): void
    {
        $this->seedRbac();
        $lesson = $this->publishedLesson();

        // A parent has no analytics.lesson.view grant.
        $this->actingAsUser($this->userWithRole('parent'));
        $this->getJson("/api/v1/lessons/{$lesson->id}/analytics")->assertStatus(403);
    }
}
