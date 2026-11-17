<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class QuizRulesTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_proverb_and_trivia_sets_can_publish_text_content_and_label_formats(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $language = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        foreach (['proverb', 'trivia'] as $kind) {
            $id = $this->postJson('/api/v1/library-lessons', [
                'title' => ucfirst($kind).' reading', 'kind' => $kind,
                'language_id' => $language->id, 'standalone_level' => 1,
                'summary' => 'A short cultural text for learners.',
            ])->assertCreated()->json('data.id');
            $this->postJson("/api/v1/lessons/{$id}/publish")->assertOk();
            $this->getJson("/api/v1/library-lessons?kind={$kind}")
                ->assertOk()->assertJsonPath('data.0.formats.0', 'text');
        }
    }

    public function test_proverb_and_trivia_sets_can_publish_video_or_quiz_content(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $language = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        foreach (['proverb', 'trivia'] as $kind) {
            foreach (['video', 'quiz'] as $format) {
                $id = $this->postJson('/api/v1/library-lessons', [
                    'title' => "{$kind} {$format}", 'kind' => $kind,
                    'language_id' => $language->id, 'standalone_level' => 1,
                ])->assertCreated()->json('data.id');
                $component = $format === 'video'
                    ? ['type' => 'video', 'video' => ['title' => 'Sample clip', 'status' => 'ready']]
                    : ['type' => 'quiz', 'quiz' => ['questions' => [[
                        'type' => 'mcq_single', 'prompt' => 'Which greeting?',
                        'options' => [['label' => 'Ndewo', 'is_correct' => true], ['label' => 'Mba', 'is_correct' => false]],
                    ]]]];
                $this->postJson("/api/v1/lessons/{$id}/components", $component)->assertCreated();
                $this->postJson("/api/v1/lessons/{$id}/publish")->assertOk();
                $this->getJson("/api/v1/library-lessons?kind={$kind}")
                    ->assertOk()->assertJsonFragment(['formats' => [$format]]);
            }
        }
    }

    public function test_author_quiz_rules_reach_the_player_and_control_xp(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $language = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        $this->postJson('/api/v1/library-lessons', [
            'title' => 'Proverb set', 'kind' => 'proverb', 'language_id' => $language->id,
            'standalone_level' => 1, 'summary' => 'Trivia-only description',
        ])->assertCreated()->assertJsonPath('data.summary', 'Trivia-only description');
        $lessonId = $this->postJson('/api/v1/library-lessons', [
            'title' => 'Proverb quiz', 'kind' => 'trivia',
            'language_id' => $language->id, 'standalone_level' => 2,
            'summary' => 'Test your knowledge of Igbo greetings.',
        ])->assertCreated()->assertJsonPath('data.course_level_id', null)
            ->assertJsonPath('data.summary', 'Test your knowledge of Igbo greetings.')->json('data.id');
        $this->patchJson("/api/v1/lessons/{$lessonId}", ['summary' => 'Explore Igbo greetings and culture.'])
            ->assertOk()->assertJsonPath('data.summary', 'Explore Igbo greetings and culture.');
        $lesson = Lesson::findOrFail($lessonId);
        $this->assertNull($lesson->course_level_id);
        $lesson->forceFill(['published_at' => now()])->save();
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

        $this->getJson('/api/v1/library-lessons?kind=trivia')->assertOk()
            ->assertJsonPath('data.0.summary', 'Explore Igbo greetings and culture.')
            ->assertJsonPath('data.0.level', 2)
            ->assertJsonPath('data.0.language', 'ig')
            ->assertJsonPath('data.0.question_count', 1)
            ->assertJsonPath('data.0.time_limit_seconds', 15);

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
