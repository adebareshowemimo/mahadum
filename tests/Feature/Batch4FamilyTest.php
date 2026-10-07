<?php

namespace Tests\Feature;

use App\Models\FamilyAlertPreference;
use App\Models\FamilyCoinPool;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\LearnerProfile;
use App\Models\LessonProgress;
use App\Notifications\FamilyActivityAlert;
use App\Services\Family\FamilyAlertService;
use App\Services\Family\WalletService;
use App\Services\Gamification\LeagueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class Batch4FamilyTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_pool_movements_conserve_coins_and_replays_survive_cache_clear(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent')->fresh());
        $child = $this->parentWithChild($parent);
        $wallets = app(WalletService::class);
        $familyWallet = $wallets->walletFor($child->family);
        $wallets->credit($familyWallet, 100, 'test');
        $id = $this->postJson('/api/v1/family/pools', ['name' => 'Family trip', 'goal_coins' => 80])->assertCreated()->json('data.id');
        $url = "/api/v1/family/pools/$id/movements";
        $this->postJson($url, ['direction' => 'contribute', 'coins' => 60], ['Idempotency-Key' => 'contribute'])->assertOk()->assertJsonPath('data.pool_balance', 60);
        Cache::flush();
        $this->postJson($url, ['direction' => 'contribute', 'coins' => 60], ['Idempotency-Key' => 'contribute'])->assertOk();
        $this->postJson($url, ['direction' => 'contribute', 'coins' => 61], ['Idempotency-Key' => 'contribute'])->assertConflict();
        $this->postJson($url, ['direction' => 'return', 'coins' => 20], ['Idempotency-Key' => 'return'])->assertOk();
        $this->postJson($url, ['direction' => 'distribute', 'coins' => 10, 'learner_id' => $child->id], ['Idempotency-Key' => 'child'])->assertOk();
        $this->postJson($url, ['direction' => 'return', 'coins' => 31], ['Idempotency-Key' => 'too-much'])->assertUnprocessable();
        $this->assertSame(60, $familyWallet->fresh()->coin_balance);
        $this->assertSame(30, $wallets->walletFor(FamilyCoinPool::findOrFail($id))->coin_balance);
        $this->assertSame(10, $wallets->walletFor($child)->coin_balance);
        $this->assertDatabaseCount('family_pool_movements', 3);
        $this->assertDatabaseCount('coin_transactions', 7);
        $this->assertDatabaseHas('family_pool_movements', ['approved_by_user_id' => $parent->id]);
    }

    public function test_pool_rejects_foreign_family_child_authority_zero_coins_and_missing_request_identity(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent')->fresh();
        $child = $this->parentWithChild($parent);
        $pool = FamilyCoinPool::create(['family_id' => $child->family_id, 'name' => 'Pool', 'goal_coins' => 10]);
        $other = $this->userWithRole('parent')->fresh();
        $foreign = $this->parentWithChild($other);
        $this->actingAsUser($other);
        $url = "/api/v1/family/pools/$pool->id/movements";
        $this->postJson($url, ['direction' => 'contribute', 'coins' => 1], ['Idempotency-Key' => 'foreign'])->assertNotFound();
        $this->actingAsUser($parent);
        $this->postJson($url, ['direction' => 'return', 'coins' => 0], ['Idempotency-Key' => 'zero'])->assertUnprocessable();
        $this->postJson($url, ['direction' => 'return', 'coins' => 1])->assertUnprocessable();
        $this->postJson($url, ['direction' => 'distribute', 'coins' => 1, 'learner_id' => $foreign->id], ['Idempotency-Key' => 'foreign-child'])->assertUnprocessable();
        $this->actingAsUser($this->userWithRole('student'));
        $this->postJson($url, ['direction' => 'return', 'coins' => 1], ['Idempotency-Key' => 'student'])->assertForbidden();
        $this->assertDatabaseCount('family_pool_movements', 0);
        $parent->update(['status' => 'suspended']);
        $this->actingAsUser($parent);
        $this->postJson($url, ['direction' => 'return', 'coins' => 1], ['Idempotency-Key' => 'suspended-parent'])->assertForbidden();
    }

    public function test_challenges_count_only_participating_family_lessons_inside_the_period_without_rewards(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent')->fresh());
        $child = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $older = $this->publishedLesson();
        LessonProgress::create(['learner_profile_id' => $child->id, 'lesson_id' => $older->id, 'status' => 'completed', 'completed_at' => now()->subDay()]);
        $this->postJson('/api/v1/family/challenges', ['title' => 'Together', 'target_lessons' => 1, 'learner_ids' => [$child->id], 'ends_at' => now()->addDays(7)->toIso8601String()])->assertCreated();
        $this->getJson('/api/v1/family/goals')->assertJsonPath('data.challenges.0.completed_lessons', 0);
        $this->travel(1)->seconds();
        LessonProgress::create(['learner_profile_id' => $child->id, 'lesson_id' => $lesson->id, 'status' => 'completed', 'completed_at' => now()]);
        $this->getJson('/api/v1/family/goals')->assertJsonPath('data.challenges.0.status', 'completed')->assertJsonPath('data.challenges.0.completed_lessons', 1);
        $this->assertDatabaseCount('coin_transactions', 0);
        $this->assertDatabaseCount('xp_ledger', 0);
        $this->postJson('/api/v1/family/challenges', ['title' => 'Foreign', 'target_lessons' => 1, 'learner_ids' => [9999], 'ends_at' => now()->addDay()->toIso8601String()])->assertUnprocessable();
        $this->assertDatabaseCount('family_challenges', 1);
    }

    public function test_cheers_are_private_once_per_week_and_never_reward_currency(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent')->fresh());
        $child = $this->parentWithChild($parent);
        $url = "/api/v1/family/learners/$child->id/cheers";
        $this->postJson($url, ['message' => 'Well done!'])->assertCreated();
        $this->postJson($url, ['message' => 'Keep going!'])->assertOk()->assertJsonPath('data.message', 'Well done!');
        $this->getJson("/api/v1/learners/$child->id/cheers")->assertOk()->assertJsonCount(1, 'data');
        $this->actingAsUser($this->userWithRole('parent')->fresh());
        $this->getJson("/api/v1/learners/$child->id/cheers")->assertForbidden();
        $this->assertDatabaseCount('family_cheers', 1);
        $this->assertDatabaseCount('xp_ledger', 0);
        $this->assertDatabaseCount('coin_transactions', 0);
    }

    public function test_alerts_default_off_and_low_balance_alert_rearms_only_after_recovery(): void
    {
        $this->seedRbac();
        Notification::fake();
        $parent = $this->actingAsUser($this->userWithRole('parent')->fresh());
        $child = $this->parentWithChild($parent);
        $service = app(FamilyAlertService::class);
        $family = $child->family;
        $service->evaluate($family);
        Notification::assertNothingSent();
        $this->putJson('/api/v1/family/alerts', ['low_balance_coins' => 5, 'inactive_days' => null, 'review_alerts' => false])->assertOk();
        $service->evaluate($family);
        $service->evaluate($family);
        Notification::assertSentToTimes($parent, FamilyActivityAlert::class, 1);
        $wallets = app(WalletService::class);
        $wallet = $wallets->walletFor($family);
        $wallets->credit($wallet, 10, 'test');
        $service->evaluate($family);
        $wallets->debit($wallet, 10, 'test');
        $service->evaluate($family);
        Notification::assertSentToTimes($parent, FamilyActivityAlert::class, 2);
    }

    public function test_inactivity_alert_uses_parent_threshold_and_never_sends_to_child(): void
    {
        $this->seedRbac();
        Notification::fake();
        $parent = $this->userWithRole('parent')->fresh();
        $child = $this->parentWithChild($parent);
        $child->update(['created_at' => now()->subDays(8)]);
        FamilyAlertPreference::create(['family_id' => $child->family_id, 'inactive_days' => 7, 'review_alerts' => false]);
        $this->artisan('family:send-alerts')->assertSuccessful();
        $this->artisan('family:send-alerts')->assertSuccessful();
        Notification::assertSentToTimes($parent, FamilyActivityAlert::class, 1);
        Notification::assertSentTo($parent, FamilyActivityAlert::class, fn ($n) => $n->kind === 'learning_inactivity');
        $this->assertSame(1, Notification::sent($parent, FamilyActivityAlert::class)->count());
    }

    public function test_weekly_leagues_are_stable_cohorts_of_thirty_and_preserve_legacy_memberships(): void
    {
        $service = app(LeagueService::class);
        $old = League::create(['week_start' => now()->subWeek()->startOfWeek(), 'tier' => 1, 'name' => 'Old']);
        $legacy = League::create(['week_start' => now()->startOfWeek(), 'tier' => 1, 'name' => 'Legacy']);
        $learners = [];
        foreach (range(1, 61) as $i) {
            $learner = LearnerProfile::create(['display_name' => "Learner $i"]);
            $learners[] = $learner;
            LeagueMembership::create(['league_id' => $legacy->id, 'learner_profile_id' => $learner->id, 'weekly_xp' => 0]);
        }
        LeagueMembership::create(['league_id' => $legacy->id, 'learner_profile_id' => $learners[0]->id, 'weekly_xp' => 0]);
        $historical = LeagueMembership::create(['league_id' => $old->id, 'learner_profile_id' => $learners[0]->id, 'weekly_xp' => 99]);
        $membership = $service->ensureMembership($learners[60]);
        $this->assertSame($membership->id, $service->ensureMembership($learners[60])->id);
        $groups = LeagueMembership::where('league_id', '!=', $old->id)->get()->groupBy('league_id')->map(fn ($rows) => $rows->pluck('learner_profile_id')->unique()->count())->values()->all();
        $this->assertSame([30, 30, 1], $groups);
        $this->assertDatabaseCount('league_memberships', 63);
        $this->assertSame(99, $historical->fresh()->weekly_xp);
    }
}
