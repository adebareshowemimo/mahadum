<?php

namespace Tests\Feature;

use App\Models\Heart;
use App\Models\LeagueMembership;
use App\Models\XpLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class QuizRetryXpTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function quizFixture(int $count = 1): array
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $component = $lesson->components->firstWhere('type', 'quiz');
        foreach (range(2, max(2, $count)) as $position) {
            if ($count === 1) {
                break;
            }
            $question = $component->quiz->questions()->create(['position' => $position, 'type' => 'mcq_single', 'prompt' => 'Pick', 'points' => 8]);
            $question->options()->create(['label' => 'Right', 'is_correct' => true, 'position' => 1]);
            $question->options()->create(['label' => 'Wrong', 'is_correct' => false, 'position' => 2]);
        }
        $questions = $component->quiz->questions()->with('options')->orderBy('position')->get();

        return [$learner, $lesson, $component, $questions];
    }

    private function payload($learner, $question, string $requestId, bool $correct = true): array
    {
        return ['learner_id' => $learner->id, 'question_id' => $question->id, 'request_id' => $requestId,
            'answer' => ['option_id' => $question->options->firstWhere('is_correct', $correct)->id]];
    }

    public function test_four_correct_then_two_correct_retry_adds_six_xp_without_changing_hearts(): void
    {
        [$learner, $lesson, $component, $questions] = $this->quizFixture(10);
        foreach ([4, 2] as $attempt => $correctCount) {
            foreach ($questions as $index => $question) {
                $this->postJson("/api/v1/components/{$component->id}/answer", $this->payload($learner, $question, "attempt-$attempt-question-$index", $index < $correctCount))
                    ->assertOk()->assertJsonPath('data.xp_awarded', $index < $correctCount ? 1 : 0);
            }
        }
        $this->assertSame(6, (int) XpLedger::where('source', 'quiz')->sum('amount'));
        $leagueId = DB::table('leagues')->insertGetId([
            'name' => 'Current week', 'tier' => 1, 'week_start' => now()->startOfWeek()->toDateString(),
        ]);
        LeagueMembership::create(['league_id' => $leagueId, 'learner_profile_id' => $learner->id, 'weekly_xp' => 0]);
        $this->getJson("/api/v1/leaderboard?learner_id={$learner->id}")->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.weekly_xp', 6)->assertJsonPath('data.0.learning_level.lifetime_xp', 6);
        $this->assertDatabaseCount('quiz_attempts', 2);
        $this->assertDatabaseHas('quiz_attempts', ['attempt_no' => 1, 'score' => 0.4]);
        $this->assertDatabaseHas('quiz_attempts', ['attempt_no' => 2, 'score' => 0.2]);
        $this->getJson("/api/v1/hearts?learner_id={$learner->id}")->assertOk()->assertJsonPath('data.current', 0);
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertStatus(423);
    }

    public function test_final_answer_replay_is_durable_and_fresh_retry_earns_again(): void
    {
        [$learner, , $component, $questions] = $this->quizFixture();
        $payload = $this->payload($learner, $questions->first(), 'final-request');
        $url = "/api/v1/components/{$component->id}/answer";
        $original = $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.xp_awarded', 1)->json();
        Cache::flush();
        $this->travel(25)->hours();
        $this->postJson($url, $payload)->assertOk()->assertHeader('Idempotency-Replayed', 'true')->assertExactJson($original);
        $this->assertDatabaseCount('quiz_attempts', 1);
        $this->assertDatabaseCount('question_responses', 1);
        $this->assertDatabaseCount('quiz_answer_receipts', 1);
        $this->assertSame(1, XpLedger::where('source', 'quiz')->count());
        $this->postJson($url, [...$payload, 'request_id' => 'intentional-retry'])->assertOk()->assertJsonPath('data.xp_awarded', 1);
        $this->assertDatabaseCount('quiz_attempts', 2);
        $this->assertSame(2, (int) XpLedger::where('source', 'quiz')->sum('amount'));
    }

    public function test_changed_answer_cannot_reuse_a_request_identity(): void
    {
        [$learner, , $component, $questions] = $this->quizFixture();
        $question = $questions->first();
        $url = "/api/v1/components/{$component->id}/answer";
        $this->postJson($url, $this->payload($learner, $question, 'same-id'))->assertOk();
        $this->postJson($url, $this->payload($learner, $question, 'same-id', false))->assertStatus(409)->assertJsonPath('error.code', 'answer_request_conflict');
        $this->assertDatabaseCount('quiz_attempts', 1);
        $this->assertSame(1, XpLedger::where('source', 'quiz')->count());
    }

    public function test_question_earns_once_within_each_attempt_including_wrong_then_correct(): void
    {
        [$learner, , $component, $questions] = $this->quizFixture(2);
        $url = "/api/v1/components/{$component->id}/answer";
        $first = $questions->first();
        foreach ([['wrong', false, 0], ['correction', true, 1], ['repeat', true, 0]] as [$id, $correct, $xp]) {
            $this->postJson($url, $this->payload($learner, $first, $id, $correct))->assertOk()->assertJsonPath('data.xp_awarded', $xp);
        }
        $this->postJson($url, $this->payload($learner, $questions->last(), 'second', false))->assertOk();
        $this->postJson($url, $this->payload($learner, $first, 'new-attempt'))->assertOk()->assertJsonPath('data.xp_awarded', 1);
        // Five transports but only three distinct answers counted toward hearts.
        $this->getJson("/api/v1/hearts?learner_id={$learner->id}")->assertOk()->assertJsonPath('data.current', 5);
        $this->assertSame(2, XpLedger::where('source', 'quiz')->count());
        $this->assertDatabaseHas('hearts', ['learner_profile_id' => $learner->id, 'questions_since_loss' => 3]);
    }

    public function test_attempt_cap_and_duplicate_final_requests_do_not_consume_extra_hearts(): void
    {
        [$learner, , $component, $questions] = $this->quizFixture();
        $component->quiz->update(['max_attempts' => 1]);
        $url = "/api/v1/components/{$component->id}/answer";
        $payload = $this->payload($learner, $questions->first(), 'scored');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.xp_awarded', 1);
        foreach (range(1, 5) as $unused) {
            $this->postJson($url, $payload)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        }
        $practice = [...$payload, 'request_id' => 'past-cap'];
        $this->postJson($url, $practice)->assertOk()->assertJsonPath('data.xp_awarded', 0)->assertJsonPath('data.attempts_exhausted', true);
        $this->postJson($url, $practice)->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('quiz_attempts', 1);
        $this->assertDatabaseCount('quiz_answer_receipts', 2);
        $this->assertSame(1, XpLedger::where('source', 'quiz')->count());
        $this->getJson("/api/v1/hearts?learner_id={$learner->id}")->assertOk()->assertJsonPath('data.current', 5);
    }

    public function test_missing_identity_is_rejected_and_receipts_never_bypass_learner_authorization(): void
    {
        [$learner, , $component, $questions] = $this->quizFixture();
        $url = "/api/v1/components/{$component->id}/answer";
        $payload = $this->payload($learner, $questions->first(), 'authorized');
        $missing = $payload;
        unset($missing['request_id']);
        $this->postJson($url, $missing)->assertUnprocessable()->assertJsonValidationErrors('request_id');
        $this->assertDatabaseCount('quiz_attempts', 0);
        $this->postJson($url, $payload)->assertOk();
        $this->actingAsUser($this->userWithRole('parent'));
        $this->postJson($url, $payload)->assertForbidden();
        $this->assertSame(1, XpLedger::where('source', 'quiz')->count());
    }

    public function test_quiz_retry_does_not_reaward_the_lesson_completion_bonus(): void
    {
        [$learner, $lesson, $component, $questions] = $this->quizFixture();
        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $lesson->courseLevel->course_id])->assertCreated();
        $url = "/api/v1/components/{$component->id}/answer";
        $this->postJson($url, $this->payload($learner, $questions->first(), 'first'))->assertOk();
        $video = $lesson->components->firstWhere('type', 'video');
        $speaking = $lesson->components->firstWhere('type', 'speaking');
        $this->postJson("/api/v1/lessons/{$lesson->id}/progress", ['learner_id' => $learner->id, 'component_id' => $video->id, 'completed' => true])->assertOk();
        $this->postJson('/api/v1/speaking-submissions', ['learner_id' => $learner->id, 'component_id' => $speaking->id])->assertCreated();
        $this->postJson("/api/v1/lessons/{$lesson->id}/complete", ['learner_id' => $learner->id])->assertOk();
        $this->postJson($url, $this->payload($learner, $questions->first(), 'retry'))->assertOk()->assertJsonPath('data.xp_awarded', 1);
        $this->postJson("/api/v1/lessons/{$lesson->id}/complete", ['learner_id' => $learner->id])->assertOk();
        $this->assertSame(2, (int) XpLedger::where('source', 'quiz')->sum('amount'));
        $this->assertSame(13, (int) XpLedger::where('source', 'lesson')->sum('amount'));
        $this->assertSame(1, XpLedger::where('source', 'lesson')->count());
    }

    public function test_receipt_identity_is_scoped_to_learner_and_does_not_bypass_hearts_lock(): void
    {
        [$learner, , $component, $questions] = $this->quizFixture();
        $other = $learner->replicate();
        $other->display_name = 'Sibling';
        $other->save();
        $url = "/api/v1/components/{$component->id}/answer";
        $first = $this->payload($learner, $questions->first(), 'shared-request');
        $this->postJson($url, $first)->assertOk()->assertJsonPath('data.xp_awarded', 1);
        $this->postJson($url, $this->payload($other, $questions->first(), 'shared-request'))->assertOk()->assertJsonPath('data.xp_awarded', 1);
        $this->assertDatabaseCount('quiz_answer_receipts', 2);
        Heart::where('learner_profile_id', $learner->id)->update(['current' => 0, 'competitive_paused_until' => now()->addHours(12)]);
        $this->postJson($url, $first)->assertStatus(423)->assertJsonPath('error.code', 'hearts_exhausted');
        $this->assertDatabaseCount('quiz_attempts', 2);
        $this->assertSame(2, XpLedger::where('source', 'quiz')->count());
    }
}
