<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Family;
use App\Models\FamilyHeroAward;
use App\Models\LearnerBadge;
use App\Models\LearnerProfile;
use App\Models\Plan;
use App\Models\Streak;
use App\Models\Subscription;
use App\Models\TelcoSubscription;
use App\Models\XpLedger;
use App\Notifications\LearningLevelUp;
use App\Services\Family\WalletService;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\LearningLevelService;
use App\Services\Gamification\StreakService;
use App\Services\Settings;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_streak_shield_requires_a_configured_price_and_debits_parent_coins_once(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $wallets = app(WalletService::class);
        $wallet = $wallets->walletFor($learner->family);
        $wallets->credit($wallet, 10, 'test_funding');

        app(Settings::class)->set(['streak.shield_price_coins' => 0]);
        $this->postJson('/api/v1/streak/shield', ['learner_id' => $learner->id])->assertUnprocessable();
        app(Settings::class)->set(['streak.shield_price_coins' => 10]);
        $this->postJson('/api/v1/streak/shield', ['learner_id' => $learner->id])
            ->assertCreated()->assertJsonPath('data.coins_spent', 10);
        $this->assertSame(0, $wallet->fresh()->coin_balance);
        $this->assertDatabaseHas('coin_transactions', ['wallet_id' => $wallet->id, 'type' => 'debit', 'source' => 'streak_shield', 'amount' => 10]);
        $this->postJson('/api/v1/streak/shield', ['learner_id' => $learner->id])->assertUnprocessable();
        $this->assertSame(1, $wallet->transactions()->where('source', 'streak_shield')->count());
    }

    public function test_child_voice_recording_is_blocked_while_invite_practice_remains_available(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $speaking = $lesson->components->firstWhere('type', 'speaking');

        $this->postJson('/api/v1/speaking-submissions', [
            'learner_id' => $learner->id,
            'component_id' => $speaking->id,
            'audio' => UploadedFile::fake()->create('voice.wav', 2, 'audio/wav'),
        ])->assertForbidden();
        $this->assertDatabaseCount('speaking_submissions', 0);
    }

    public function test_telco_grace_protects_a_missed_streak_day_only_within_the_forty_eight_hour_window(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        $plan = Plan::create(['code' => 'streak-airtime', 'name' => 'Airtime', 'price_minor' => 30000,
            'currency' => 'NGN', 'interval' => 'month', 'max_profiles' => 1]);
        $subscription = new Subscription(['plan_id' => $plan->id, 'method' => 'airtime',
            'status' => 'grace', 'started_at' => now()->subWeek()]);
        $subscription->subscriber()->associate($parent);
        $subscription->save();
        $telco = TelcoSubscription::create(['subscription_id' => $subscription->id, 'msisdn' => '08031234567',
            'operator' => 'mtn', 'daily_amount_minor' => 1000, 'state' => 'grace',
            'grace_until' => now()->addDay()]);
        Streak::create(['learner_profile_id' => $learner->id, 'current_count' => 4,
            'longest_count' => 4, 'last_active_date' => today()->subDays(2), 'state' => 'active']);

        $this->assertSame(5, app(StreakService::class)->recordActivity($learner)->current_count);
        $this->assertSame(5, app(StreakService::class)->recordActivity($learner)->current_count);
        $telco->update(['grace_until' => now()->subMinute()]);
        $streak = Streak::where('learner_profile_id', $learner->id)->firstOrFail();
        $streak->update(['current_count' => 4, 'last_active_date' => today()->subDays(2)]);
        $this->assertSame(1, app(StreakService::class)->recordActivity($learner)->current_count);
    }

    public function test_completing_a_lesson_bumps_streak_and_awards_badges(): void
    {
        Notification::fake();
        $this->seedRbac();
        $this->seed(BadgeSeeder::class);

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
        $this->answerJson("/api/v1/components/{$quizC->id}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question->id, 'answer' => ['option_id' => $correct->id],
        ])->assertOk();
        $this->postJson("/api/v1/lessons/{$lesson->id}/progress", [
            'learner_id' => $learner->id, 'component_id' => $videoC->id, 'completed' => true,
        ])->assertOk();
        $this->postJson('/api/v1/speaking-submissions', [
            'learner_id' => $learner->id, 'component_id' => $speakC->id,
        ])->assertCreated();

        $complete = $this->postJson("/api/v1/lessons/{$lesson->id}/complete", ['learner_id' => $learner->id])->assertOk();
        $complete->assertJsonPath('data.streak.count', 1);

        $codes = collect($complete->json('data.badges_unlocked'))->pluck('code');
        $this->assertTrue($codes->contains('first_lesson'));
        $this->assertTrue($codes->contains('sharp_shooter'));
        $this->assertTrue($codes->contains('tier_0'));
        Notification::assertSentTo($parent, LearningLevelUp::class, fn ($notification, $channels) => $notification->toArray($parent)['badge_name'] === 'Star Starter'
            && $notification->toArray($parent)['level'] === 0
            && in_array('database', $channels, true));

        $this->getJson("/api/v1/learners/{$learner->id}/streak")->assertOk()->assertJsonPath('data.count', 1);
        $this->getJson("/api/v1/learners/{$learner->id}/badges")->assertOk()
            ->assertJsonFragment(['code' => 'first_lesson']);
        $this->assertNotNull(LearnerBadge::where('learner_profile_id', $learner->id)
            ->whereHas('badge', fn ($q) => $q->where('code', 'tier_0'))->firstOrFail()->earned_at);
    }

    public function test_hearts_never_block_and_refill(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);

        $this->getJson("/api/v1/hearts?learner_id={$learner->id}")->assertOk()->assertJsonPath('data.current', 5);
        $this->postJson('/api/v1/hearts/refill', ['learner_id' => $learner->id, 'method' => 'coins'])
            ->assertStatus(422);
    }

    public function test_every_four_answers_cost_one_heart_and_zero_locks_for_twelve_hours(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $quiz = $lesson->components->firstWhere('type', 'quiz');
        $question = $quiz->quiz->questions->first();
        $correct = $question->options->firstWhere('is_correct', true);
        foreach (range(1, 20) as $number) {
            $this->answerJson("/api/v1/components/{$quiz->id}/answer", [
                'learner_id' => $learner->id, 'question_id' => $question->id,
                'answer' => ['option_id' => $correct->id],
            ])->assertOk()->assertJsonPath('data.hearts_remaining', 5 - intdiv($number, 4));
        }
        $this->answerJson("/api/v1/components/{$quiz->id}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question->id,
            'answer' => ['option_id' => $correct->id],
        ])->assertStatus(423)->assertJsonPath('error.code', 'hearts_exhausted');
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertStatus(423);
        $this->postJson("/api/v1/lessons/{$lesson->id}/complete", ['learner_id' => $learner->id])->assertStatus(423);
        $this->travel(12)->hours();
        $this->travel(1)->seconds();
        $this->getJson("/api/v1/hearts?learner_id={$learner->id}")->assertOk()->assertJsonPath('data.current', 5);
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk();
    }

    public function test_lifetime_xp_uses_the_approved_level_thresholds(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        XpLedger::create(['learner_profile_id' => $learner->id, 'amount' => 1500, 'source' => 'test']);

        $level = app(LearningLevelService::class)->forLearner($learner->fresh());
        $this->assertSame(['number' => 3, 'name' => 'Gold', 'lifetime_xp' => 1500, 'next_level_xp' => 4000], $level);
        $this->assertSame(3, $learner->fresh()->current_level);
    }

    public function test_reaching_a_level_awards_the_tier_badge_and_notifies_the_learner(): void
    {
        Notification::fake();
        $this->seedRbac();
        $this->seed(BadgeSeeder::class);

        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);

        $intro = $this->publishedLesson();
        $unit = $intro->courseLevel->course->levels()->create(['title' => 'Level 1', 'position' => 1])->lessons()->create(['title' => 'Level 1 lesson', 'position' => 2, 'published_at' => now()]);
        foreach ([$intro, $unit] as $lesson) {
            $learner->lessonProgress()->create(['lesson_id' => $lesson->id, 'status' => 'completed', 'completed_at' => now()]);
        }
        // XP alone must not grant content-completion badges.
        XpLedger::create(['learner_profile_id' => $learner->id, 'amount' => 120, 'source' => 'test']);
        $new = app(BadgeService::class)->evaluate($learner->fresh());

        $codes = collect($new)->pluck('code');
        $this->assertTrue($codes->contains('tier_0'));
        $this->assertTrue($codes->contains('tier_1'));
        $this->assertDatabaseHas('learner_badges', ['learner_profile_id' => $learner->id]);

        Notification::assertSentTo(
            $parent,
            LearningLevelUp::class,
        );

        // Idempotent — a second evaluate awards nothing and notifies no-one new.
        $this->assertSame([], app(BadgeService::class)->evaluate($learner->fresh()));
    }

    public function test_badge_endpoint_returns_detail_fields_for_earned_and_locked(): void
    {
        $this->seedRbac();
        $this->seed(BadgeSeeder::class);
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);

        $lesson = $this->publishedLesson();
        $learner->lessonProgress()->create(['lesson_id' => $lesson->id, 'status' => 'completed', 'completed_at' => now()]);
        app(BadgeService::class)->evaluate($learner->fresh());

        $this->getJson("/api/v1/learners/{$learner->id}/badges")
            ->assertOk()
            ->assertJsonFragment(['code' => 'tier_0', 'name' => 'Star Starter', 'level' => 0])
            ->assertJsonStructure(['data' => ['earned' => [['description', 'icon', 'earned_at']], 'locked' => [['description', 'icon']]]]);
    }

    public function test_family_hero_awards_all_daily_xp_ties_and_is_idempotent(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Heroes', 'timezone' => 'Africa/Lagos']);
        $one = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'One', 'current_level' => 0]);
        $two = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Two', 'current_level' => 0]);
        $earnedAt = now('Africa/Lagos')->subDay()->setTime(12, 0)->utc();
        XpLedger::create(['learner_profile_id' => $one->id, 'amount' => 40, 'source' => 'lesson', 'created_at' => $earnedAt]);
        XpLedger::create(['learner_profile_id' => $two->id, 'amount' => 40, 'source' => 'quiz', 'created_at' => $earnedAt]);
        $date = now('Africa/Lagos')->subDay()->toDateString();

        $this->artisan("gamification:award-family-heroes --date={$date}")->assertSuccessful();
        $this->artisan("gamification:award-family-heroes --date={$date}")->assertSuccessful();

        $this->assertSame(2, FamilyHeroAward::where('family_id', $family->id)->count());
        $this->assertDatabaseHas('learner_badges', ['learner_profile_id' => $one->id]);
        $this->assertDatabaseHas('learner_badges', ['learner_profile_id' => $two->id]);
    }
}
