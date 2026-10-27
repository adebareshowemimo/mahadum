<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class QuizRulesTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_author_quiz_rules_reach_the_player_and_control_xp(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $language = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        $course = Course::create(['language_id' => $language->id, 'title' => 'Trivia', 'status' => 'published', 'is_published' => true]);
        $level = $course->levels()->create(['title' => 'Free', 'position' => 0, 'is_free' => true]);
        $lesson = $level->lessons()->create(['title' => 'Proverb quiz', 'kind' => 'trivia', 'position' => 1, 'published_at' => now()]);
        $component = $this->postJson("/api/v1/lessons/{$lesson->id}/components", [
            'type' => 'quiz', 'quiz' => [
                'pass_threshold' => 0.8, 'max_attempts' => 2, 'hearts_enabled' => false,
                'shuffle_questions' => true, 'time_limit_seconds' => 15, 'xp_per_correct' => 3,
                'questions' => [['type' => 'mcq_single', 'prompt' => 'Which greeting?', 'options' => [
                    ['label' => 'Ndewo', 'is_correct' => true], ['label' => 'Mba', 'is_correct' => false],
                ]]],
            ],
        ])->assertCreated()->assertJsonPath('data.detail.time_limit_seconds', 15)
            ->assertJsonPath('data.detail.xp_per_correct', 3)->json('data');

        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $play = $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")
            ->assertOk()->assertJsonPath('data.components.0.quiz.time_limit_seconds', 15)
            ->assertJsonPath('data.components.0.quiz.max_attempts', 2)->json('data');
        $question = $play['components'][0]['quiz']['questions'][0];
        $correct = $component['detail']['questions'][0]['options'][0]['id'];
        $this->answerJson("/api/v1/components/{$component['id']}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question['id'], 'answer' => ['option_id' => $correct],
        ])->assertOk()->assertJsonPath('data.xp_awarded', 3);
    }
}
