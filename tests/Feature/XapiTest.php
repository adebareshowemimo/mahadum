<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Language;
use App\Models\XapiStatement;
use App\Services\Learning\XapiRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class XapiTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_learning_flow_emits_statements_for_every_event(): void
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
        $this->postJson("/api/v1/lessons/{$lesson->id}/complete", ['learner_id' => $learner->id])->assertOk();

        foreach ([
            XapiRecorder::VERB_REGISTERED,
            XapiRecorder::VERB_ANSWERED,
            XapiRecorder::VERB_COMPLETED,
            XapiRecorder::VERB_RESPONDED,
        ] as $verb) {
            $this->assertDatabaseHas('xapi_statements', ['learner_profile_id' => $learner->id, 'verb' => $verb]);
        }

        // The answered statement carries a success result + learner agent.
        $answered = XapiStatement::where('verb', XapiRecorder::VERB_ANSWERED)->firstOrFail();
        $this->assertTrue($answered->raw['result']['success']);
        $this->assertSame('Activity', $answered->raw['object']['objectType']);
        $this->assertStringContainsString('learner:'.$learner->id, $answered->raw['actor']['account']['name']);

        // The lesson-completion statement has a full scaled score.
        $completedLesson = XapiStatement::where('verb', XapiRecorder::VERB_COMPLETED)
            ->where('object_iri', 'like', '%/lessons/%')->firstOrFail();
        $this->assertEquals(1.0, $completedLesson->raw['result']['score']['scaled']);

        // Nothing is pushed to an LRS — all rows remain unsynced.
        $this->assertSame(0, XapiStatement::whereNotNull('lrs_synced_at')->count());
    }

    public function test_assessment_emits_completed_statement_with_level(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $language = Language::firstOrCreate(['code' => 'ig'], ['name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);

        $this->postJson('/api/v1/assessments', [
            'learner_id' => $learner->id, 'language_id' => $language->id, 'score' => 0.9,
        ])->assertOk()->assertJsonPath('data.result_level', 'B1');

        $statement = XapiStatement::where('object_iri', 'like', '%/assessments/%')->firstOrFail();
        $this->assertEquals(0.9, $statement->raw['result']['score']['scaled']);

        $levelKey = app(XapiRecorder::class)->iri('ext', 'level');
        $this->assertSame('B1', $statement->raw['result']['extensions'][$levelKey]);
    }
}
