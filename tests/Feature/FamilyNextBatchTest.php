<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\LearnerProfile;
use App\Models\LessonProgress;
use App\Models\SpeakingSubmission;
use App\Models\Streak;
use App\Models\WalletFundingTransaction;
use App\Services\Family\WalletService;
use App\Services\Gamification\LeagueService;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class FamilyNextBatchTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_add_child_enrolls_in_the_chosen_published_language(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        Family::create(['owner_user_id' => $parent->id, 'name' => 'Family']);
        $lesson = $this->publishedLesson();
        $id = $this->postJson('/api/v1/family/children', [
            'display_name' => 'Learner', 'target_language_id' => $lesson->courseLevel->course->language_id,
            'consent' => true,
        ])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('enrollments', ['learner_profile_id' => $id, 'course_id' => $lesson->courseLevel->course_id]);
        $this->getJson("/api/v1/family/children/{$id}")->assertOk()->assertJsonPath('data.target_language', 'ig');
    }

    public function test_parent_can_approve_and_remove_a_practice_partner_for_one_child(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $otherChild = LearnerProfile::create(['family_id' => $child->family_id, 'display_name' => 'Sibling']);
        $teacher = $this->userWithRole('teacher', ['email_verified_at' => now()]);
        $this->getJson("/api/v1/learners/{$child->id}/practice-contacts")->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/family/children/{$child->id}/practice-partners", ['email' => $teacher->email])->assertCreated();
        $this->getJson("/api/v1/learners/{$child->id}/practice-contacts")->assertOk()->assertJsonPath('data.0.id', $teacher->id);
        $this->getJson("/api/v1/learners/{$otherChild->id}/practice-contacts")->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson("/api/v1/family/children/{$child->id}/practice-partners/{$teacher->id}")->assertNoContent();
        $this->getJson("/api/v1/learners/{$child->id}/practice-contacts")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_family_dashboard_shows_recorded_streak_speaking_score_and_rank(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $this->postJson('/api/v1/enrollments', ['learner_id' => $child->id, 'course_id' => $lesson->courseLevel->course_id])->assertCreated();
        LessonProgress::create(['learner_profile_id' => $child->id, 'lesson_id' => $lesson->id,
            'status' => 'completed', 'started_at' => now()->subMinutes(5), 'completed_at' => now()]);
        Streak::create(['learner_profile_id' => $child->id, 'current_count' => 3, 'longest_count' => 3, 'last_active_date' => today()]);
        SpeakingSubmission::create(['learner_profile_id' => $child->id, 'lesson_component_id' => $lesson->components->firstWhere('type', 'speaking')->id,
            'ai_score' => 82.5, 'status' => 'reviewed']);
        app(LeagueService::class)->ensureMembership($child);

        $this->getJson('/api/v1/family')->assertOk()
            ->assertJsonPath('data.learners.0.lessons_completed', 1)
            ->assertJsonPath('data.learners.0.streak_days', 3)
            ->assertJsonPath('data.learners.0.speaking_score', 82.5)
            ->assertJsonPath('data.learners.0.rank', 1);
    }

    public function test_parent_sets_a_course_start_before_learning_without_faking_completion(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $course = $lesson->courseLevel->course;
        $secondLevel = $course->levels()->create(['title' => 'Level 1', 'position' => 1, 'is_free' => false]);
        $second = $secondLevel->lessons()->create(['title' => 'Next lesson', 'position' => 1, 'published_at' => now()]);
        $this->postJson('/api/v1/enrollments', ['learner_id' => $child->id, 'course_id' => $course->id])->assertCreated();

        $this->putJson("/api/v1/family/children/{$child->id}/starting-level", ['position' => 1])
            ->assertOk()->assertJsonPath('data.starting_level_position', 1);
        $this->assertDatabaseMissing('learner_path_nodes', ['lesson_id' => $lesson->id]);
        $this->assertDatabaseHas('learner_path_nodes', ['lesson_id' => $second->id, 'state' => 'active']);
        $this->assertDatabaseCount('lesson_progress', 0);
        LessonProgress::create(['learner_profile_id' => $child->id, 'lesson_id' => $second->id,
            'status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);
        $this->putJson("/api/v1/family/children/{$child->id}/starting-level", ['position' => 0])->assertStatus(409);
    }

    public function test_five_minute_reward_waits_for_funded_parent_coins_and_pays_once(): void
    {
        Notification::fake();
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $this->postJson('/api/v1/enrollments', ['learner_id' => $child->id, 'course_id' => $lesson->courseLevel->course_id])->assertCreated();
        $this->travelTo(now()->startOfDay()->addHours(12));
        for ($i = 0; $i <= 10; $i++) {
            $response = $this->postJson('/api/v1/learning/study/heartbeat', ['learner_id' => $child->id, 'lesson_id' => $lesson->id])->assertOk();
            if ($i < 10) {
                $this->travel(30)->seconds();
            }
        }
        $response->assertJsonPath('data.status', 'pending')->assertJsonPath('data.progress_seconds', 300);
        $this->assertDatabaseMissing('coin_transactions', ['source' => 'study']);
        $wallets = app(WalletService::class);
        $wallets->creditCurrency($wallets->walletFor($child->family), 1000);
        app(Settings::class)->set(['wallet.coin_price_minor' => 100]);
        $this->postJson('/api/v1/wallet/coins', ['coins' => 10], ['Idempotency-Key' => 'buy-10'])
            ->assertOk()->assertJsonPath('data.amount_minor', 1000);
        $this->postJson('/api/v1/learning/study/heartbeat', ['learner_id' => $child->id, 'lesson_id' => $lesson->id])
            ->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson('/api/v1/learning/study/heartbeat', ['learner_id' => $child->id, 'lesson_id' => $lesson->id])
            ->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertSame(0, $wallets->walletFor($child->family)->fresh()->coin_balance);
        $this->assertSame(10, $wallets->walletFor($child)->fresh()->coin_balance);
        $this->assertSame(2, DB::table('coin_transactions')->where('source', 'study')->count());
    }

    public function test_admin_priced_coin_purchase_uses_verified_wallet_cash_once_and_reports_it(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $wallets = app(WalletService::class);
        $wallet = $wallets->walletFor($child->family);
        $wallets->creditCurrency($wallet, 1500);
        WalletFundingTransaction::create([
            'wallet_id' => $wallet->id, 'gateway' => 'flutterwave', 'amount_minor' => 1500,
            'currency' => 'NGN', 'status' => 'success', 'gateway_ref' => 'verified-coin-funding',
        ]);

        $this->postJson('/api/v1/wallet/coins', ['coins' => 10], ['Idempotency-Key' => 'coin-order-1'])->assertStatus(503);
        app(Settings::class)->set(['wallet.coin_price_minor' => 100]);
        $this->postJson('/api/v1/wallet/coins', ['coins' => 20], ['Idempotency-Key' => 'coin-order-2'])->assertUnprocessable();
        $this->postJson('/api/v1/wallet/coins', ['coins' => 10], ['Idempotency-Key' => 'coin-order-1'])
            ->assertOk()->assertJsonPath('data.amount_minor', 1000)->assertJsonPath('data.coin_balance', 10);
        app(Settings::class)->set(['wallet.coin_price_minor' => 200]);
        $this->postJson('/api/v1/wallet/coins', ['coins' => 10], ['Idempotency-Key' => 'coin-order-1'])
            ->assertOk()->assertJsonPath('data.amount_minor', 1000)->assertJsonPath('data.coin_balance', 10);
        $this->postJson('/api/v1/wallet/coins', ['coins' => 9], ['Idempotency-Key' => 'coin-order-1'])->assertStatus(409);
        $this->assertSame(500, $wallet->fresh()->currency_balance_minor);
        $this->assertDatabaseCount('wallet_coin_purchases', 1);
        $this->assertDatabaseCount('wallet_currency_transactions', 1);
        $this->getJson('/api/v1/wallet')->assertOk()
            ->assertJsonPath('data.report.cash.coin_purchases_minor', 1000)
            ->assertJsonPath('data.report.cash.unexplained_minor', 0)
            ->assertJsonPath('data.report.cash.reconciled', true);
    }
}
