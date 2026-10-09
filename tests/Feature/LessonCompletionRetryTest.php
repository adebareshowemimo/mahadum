<?php

namespace Tests\Feature;

use App\Models\ComponentProgress;
use App\Models\LearnerBadge;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\XpLedger;
use App\Services\Learning\LessonScorer;
use App\Services\Learning\XapiRecorder;
use App\Services\Referral\ReferralService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class LessonCompletionRetryTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function ready(): array
    {
        $this->seedRbac();
        $this->seed(BadgeSeeder::class);
        Notification::fake();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $lesson->courseLevel->course_id])->assertCreated();
        $progress = LessonProgress::create(['learner_profile_id' => $learner->id, 'lesson_id' => $lesson->id, 'status' => 'in_progress']);
        foreach ($lesson->components as $component) {
            ComponentProgress::create(['lesson_progress_id' => $progress->id, 'lesson_component_id' => $component->id, 'status' => 'complete', 'score' => 1]);
        }

        return [$learner, $lesson, $progress];
    }

    public function test_referral_failure_after_completion_does_not_hide_first_steps_or_trigger_another_reward(): void
    {
        [$learner, $lesson, $progress] = $this->ready();
        $this->mock(ReferralService::class)->shouldReceive('maybeActivateForLearner')->twice()
            ->andThrow(new \RuntimeException('Simulated referral persistence failure'));
        $url = "/api/v1/lessons/{$lesson->id}/complete";
        $this->postJson($url, ['learner_id' => $learner->id])->assertOk()
            ->assertJsonPath('data.streak.count', 1)->assertJsonPath('data.xp_total', 13)
            ->assertJsonFragment(['code' => 'first_lesson', 'name' => 'First Steps']);
        $this->postJson($url, ['learner_id' => $learner->id])->assertOk()
            ->assertJsonPath('data.xp_total', 0)->assertJsonCount(0, 'data.badges_unlocked');
        $this->assertDatabaseCount('xp_ledger', 1);
        $this->assertSame('completed', $progress->fresh()->status);
    }

    public function test_completion_retries_award_xp_and_first_steps_once(): void
    {
        [$learner, $lesson, $progress] = $this->ready();
        $url = "/api/v1/lessons/{$lesson->id}/complete";
        $this->postJson($url, ['learner_id' => $learner->id])->assertOk()->assertJsonPath('data.xp_total', 13)
            ->assertJsonPath('data.streak.count', 1)->assertJsonFragment(['code' => 'first_lesson', 'name' => 'First Steps']);
        $completed = $progress->fresh()->completed_at->toIso8601String();
        $this->postJson($url, ['learner_id' => $learner->id])->assertOk()->assertJsonPath('data.xp_total', 0)
            ->assertJsonPath('data.streak.count', 1)->assertJsonCount(0, 'data.badges_unlocked');
        $this->assertSame($completed, $progress->fresh()->completed_at->toIso8601String());
        $this->assertDatabaseCount('xp_ledger', 1);
        $this->assertDatabaseHas('learner_badges', ['learner_profile_id' => $learner->id]);
        $this->assertDatabaseCount('streaks', 1);
        $this->assertSame(1, LearnerBadge::where('learner_profile_id', $learner->id)->whereHas('badge', fn ($query) => $query->where('code', 'first_lesson'))->count());
        $this->assertDatabaseCount('xapi_statements', 2); // enrollment + lesson completion
    }

    public function test_a_stale_request_snapshot_cannot_issue_a_second_lesson_reward(): void
    {
        [$learner, $lesson, $progress] = $this->ready();
        $scorer = new LessonScorer;
        $this->mock(LessonScorer::class, function ($mock) use ($scorer, $learner, $lesson, $progress) {
            $mock->shouldReceive('incompleteRequired')->once()->andReturn([]);
            $mock->shouldReceive('score')->once()->andReturnUsing(function ($currentLesson, $components) use ($scorer, $learner, $lesson, $progress) {
                // Deterministically interleave another worker's completed commit
                // after this request read progress, before it writes the reward.
                LessonProgress::whereKey($progress->id)->update(['status' => 'completed', 'completed_at' => now()]);
                XpLedger::create(['learner_profile_id' => $learner->id, 'source' => 'lesson', 'amount' => 13,
                    'reference_type' => Lesson::class, 'reference_id' => $lesson->id]);
                app(XapiRecorder::class)->record($learner->id, XapiRecorder::VERB_COMPLETED, 'lessons', $lesson->id);

                return $scorer->score($currentLesson, $components);
            });
        });
        $this->postJson("/api/v1/lessons/{$lesson->id}/complete", ['learner_id' => $learner->id])
            ->assertOk()->assertJsonPath('data.xp_total', 0);
        $this->assertDatabaseCount('xp_ledger', 1);
        $this->assertDatabaseCount('xapi_statements', 2);
    }

    public function test_failed_completion_event_rolls_back_reward_and_allows_a_clean_retry(): void
    {
        [$learner, $lesson, $progress] = $this->ready();
        $this->mock(XapiRecorder::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Simulated persistence failure'));
        $url = "/api/v1/lessons/{$lesson->id}/complete";
        $this->postJson($url, ['learner_id' => $learner->id])->assertStatus(500);
        $this->assertSame('in_progress', $progress->fresh()->status);
        $this->assertDatabaseCount('xp_ledger', 0);
        $this->assertDatabaseCount('learner_badges', 0);
        $this->app->instance(XapiRecorder::class, new XapiRecorder);
        $this->postJson($url, ['learner_id' => $learner->id])->assertOk()->assertJsonPath('data.xp_total', 13)
            ->assertJsonFragment(['code' => 'first_lesson', 'name' => 'First Steps']);
        $this->assertDatabaseCount('xp_ledger', 1);
    }

    public function test_earned_badges_and_completion_response_survive_notification_queue_failure(): void
    {
        [$learner, $lesson, $progress] = $this->ready();
        Notification::shouldReceive('send')->twice()->andThrow(new \RuntimeException('Simulated queue failure'));
        $url = "/api/v1/lessons/{$lesson->id}/complete";
        $this->postJson($url, ['learner_id' => $learner->id])->assertOk()->assertJsonPath('data.xp_total', 13)
            ->assertJsonFragment(['code' => 'first_lesson', 'name' => 'First Steps'])
            ->assertJsonFragment(['code' => 'tier_0', 'name' => 'Star Starter']);
        $this->assertSame('completed', $progress->fresh()->status);
        $this->postJson($url, ['learner_id' => $learner->id])->assertOk()->assertJsonPath('data.xp_total', 0)
            ->assertJsonCount(0, 'data.badges_unlocked');
        $this->assertDatabaseCount('xp_ledger', 1);
        $this->assertSame(2, LearnerBadge::where('learner_profile_id', $learner->id)->count());
    }

    public function test_badge_persistence_failure_rolls_back_completion_and_retry_returns_all_first_awards(): void
    {
        [$learner, $lesson, $progress] = $this->ready();
        $fail = true;
        LearnerBadge::creating(function (LearnerBadge $award) use (&$fail) {
            if ($fail && $award->badge->code === 'tier_0') {
                throw new \RuntimeException('Simulated badge persistence failure');
            }
        });
        $url = "/api/v1/lessons/{$lesson->id}/complete";

        $this->postJson($url, ['learner_id' => $learner->id])->assertStatus(500);
        $this->assertSame('in_progress', $progress->fresh()->status);
        $this->assertNull($progress->fresh()->completed_at);
        $this->assertDatabaseCount('xp_ledger', 0);
        $this->assertDatabaseCount('learner_badges', 0);
        $this->assertDatabaseCount('streaks', 0);
        $this->assertDatabaseCount('xapi_statements', 1); // Enrollment only.
        $this->assertDatabaseMissing('learner_path_nodes', ['lesson_id' => $lesson->id, 'state' => 'completed']);
        Notification::assertNothingSent();

        $fail = false;
        $this->postJson($url, ['learner_id' => $learner->id])->assertOk()
            ->assertJsonPath('data.xp_total', 13)->assertJsonPath('data.streak.count', 1)
            ->assertJsonFragment(['code' => 'first_lesson', 'name' => 'First Steps'])
            ->assertJsonFragment(['code' => 'tier_0', 'name' => 'Star Starter']);
        $earnedAt = LearnerBadge::where('learner_profile_id', $learner->id)->firstOrFail()->earned_at->toISOString();
        $this->postJson($url, ['learner_id' => $learner->id])->assertOk()
            ->assertJsonPath('data.xp_total', 0)->assertJsonCount(0, 'data.badges_unlocked');
        $this->assertDatabaseCount('xp_ledger', 1);
        $this->assertDatabaseCount('learner_badges', 2);
        $this->assertDatabaseCount('streaks', 1);
        $this->assertDatabaseCount('xapi_statements', 2);
        $this->assertSame($earnedAt, LearnerBadge::where('learner_profile_id', $learner->id)->firstOrFail()->earned_at->toISOString());
    }
}
