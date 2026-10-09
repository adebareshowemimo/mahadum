<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LearnerProfile;
use App\Models\Plan;
use App\Models\QuizAttempt;
use App\Models\ReferralCode;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class ReferralFraudTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_velocity_flags_only_more_than_fifteen_recent_signups_and_lists_them_for_review(): void
    {
        $this->freezeTime();
        $this->seedRbac();
        $admin = $this->userWithRole('super_admin');
        $service = app(ReferralService::class);
        $safe = $service->codeFor($this->userWithRole('parent'));
        $flagged = $service->codeFor($this->userWithRole('parent'));

        foreach ([$safe, $flagged] as $code) {
            foreach (range(1, 15) as $number) {
                $code->referrals()->create(['status' => 'pending', 'signed_up_at' => now()]);
            }
        }
        $safe->referrals()->create(['status' => 'pending', 'signed_up_at' => now()->subDay()->subSecond()]);
        $flagged->referrals()->create(['status' => 'pending', 'signed_up_at' => now()]);

        $this->artisan('referrals:flag-velocity')->assertSuccessful();
        $this->assertSame('active', $safe->fresh()->status);
        $this->assertSame('flagged', $flagged->fresh()->status);
        $this->actingAsUser($admin);
        $this->getJson('/api/v1/admin/referrals/flagged')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', $flagged->code)
            ->assertJsonPath('data.0.status', 'flagged')
            ->assertJsonPath('data.0.referrals_24h', 16);

        $this->artisan('referrals:flag-velocity')->assertSuccessful();
        $this->assertSame('flagged', $flagged->fresh()->status);
    }

    public function test_flagged_and_frozen_codes_block_attribution_activation_and_commission_until_cleared(): void
    {
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        $admin = $this->userWithRole('super_admin');
        $service = app(ReferralService::class);
        $code = $service->codeFor($this->userWithRole('parent'));
        $referred = $this->userWithRole('parent');
        $referral = $service->attribute($referred, $code->code, 'eligible-device');
        $subscription = $this->completedLearningWithSubscription($referred, 'premium_individual');
        $newUser = $this->userWithRole('parent');
        $code->update(['status' => 'flagged']);
        $this->actingAsUser($admin);

        foreach (['flagged', 'frozen'] as $status) {
            if ($status === 'frozen') {
                $this->postJson("/api/v1/admin/referrals/{$code->id}/freeze")->assertOk()
                    ->assertJsonPath('data.status', 'frozen');
            }
            $this->assertNull($service->attribute($newUser, $code->code, 'fresh-device'));
            $service->maybeActivateForUser($referred);
            $this->assertSame('pending', $referral->fresh()->status);

            // An already-qualified referral must also stop earning during review.
            $referral->update(['status' => 'qualified', 'activated_at' => now()]);
            $this->assertNull($service->recordReferredPurchase($referred, $subscription, 200_000, 'blocked-'.$status));
            $referral->update(['status' => 'pending', 'activated_at' => null]);
        }
        $this->assertDatabaseCount('commissions', 0);
        $this->getJson('/api/v1/admin/referrals/flagged')->assertOk()->assertJsonPath('data.0.status', 'frozen');

        $this->postJson("/api/v1/admin/referrals/{$code->id}/clear")->assertOk()->assertJsonPath('data.status', 'active');
        $this->getJson('/api/v1/admin/referrals/flagged')->assertOk()->assertJsonCount(0, 'data');
        $service->maybeActivateForUser($referred);
        $this->assertSame('qualified', $referral->fresh()->status);
        $this->assertNotNull($service->attribute($newUser, $code->code, 'fresh-device'));
        $this->assertNotNull($service->recordReferredPurchase($referred, $subscription, 200_000, 'after-clear'));

        foreach (['referral.frozen', 'referral.cleared'] as $action) {
            $audit = AuditLog::where('action', $action)->where('subject_id', $code->id)->firstOrFail();
            $this->assertSame($admin->id, $audit->actor_user_id);
            $this->assertSame(ReferralCode::class, $audit->subject_type);
            $this->assertSame($action === 'referral.frozen' ? 'frozen' : 'active', $audit->after['status']);
        }
    }

    public function test_free_subscription_cannot_activate_a_referral_and_reused_devices_never_qualify(): void
    {
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        $service = app(ReferralService::class);
        $code = $service->codeFor($this->userWithRole('parent'));
        $first = $this->userWithRole('parent');
        $second = $this->userWithRole('parent');
        $pending = $service->attribute($first, $code->code, 'shared-device');
        $rejected = $service->attribute($second, $code->code, 'shared-device');
        $subscription = $this->completedLearningWithSubscription($first, 'free');
        $this->completedLearningWithSubscription($second, 'premium_individual');

        $service->maybeActivateForUser($first);
        $service->maybeActivateForUser($second);
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertNull($rejected->fresh()->activated_at);

        $subscription->update(['plan_id' => Plan::where('code', 'premium_individual')->firstOrFail()->id]);
        $service->maybeActivateForUser($first);
        $this->assertSame('qualified', $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->activated_at);
    }

    public function test_parent_cannot_list_clear_or_freeze_fraud_codes(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $code = app(ReferralService::class)->codeFor($parent);
        $code->update(['status' => 'flagged']);

        $this->getJson('/api/v1/admin/referrals/flagged')->assertForbidden();
        $this->postJson("/api/v1/admin/referrals/{$code->id}/clear")->assertForbidden();
        $this->postJson("/api/v1/admin/referrals/{$code->id}/freeze")->assertForbidden();
        $this->assertSame('flagged', $code->fresh()->status);
    }

    public function test_activation_rechecks_code_status_after_a_pending_referral_was_loaded(): void
    {
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        $service = app(ReferralService::class);
        $code = $service->codeFor($this->userWithRole('parent'));
        $buyer = $this->userWithRole('parent');
        $pending = $service->attribute($buyer, $code->code, 'freeze-before-activation');
        $this->completedLearningWithSubscription($buyer, 'premium_individual');
        $pending->load('referralCode');

        foreach (['flagged', 'frozen'] as $status) {
            $code->update(['status' => $status]);
            $service->maybeActivate($pending);
            $this->assertSame('pending', $pending->fresh()->status);
            $this->assertNull($pending->fresh()->activated_at);
        }

        $code->update(['status' => 'active']);
        $service->maybeActivate($pending);
        $this->assertSame('qualified', $pending->fresh()->status);
    }

    public function test_stale_pending_activation_does_not_restart_the_commission_window(): void
    {
        $this->freezeTime();
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        $service = app(ReferralService::class);
        $code = $service->codeFor($this->userWithRole('parent'));
        $buyer = $this->userWithRole('parent');
        $stale = $service->attribute($buyer, $code->code, 'stale-activation');
        $subscription = $this->completedLearningWithSubscription($buyer, 'premium_individual');
        $service->maybeActivate($stale->fresh());
        $activatedAt = $stale->fresh()->activated_at;
        $this->travel(31)->days();

        $service->maybeActivate($stale);

        $this->assertTrue($stale->fresh()->activated_at->equalTo($activatedAt));
        $this->assertNull($service->recordReferredPurchase($buyer, $subscription, 200_000, 'outside-original-window'));
        $this->assertDatabaseCount('commissions', 0);
    }

    public function test_stale_pending_activation_cannot_restore_a_reversed_referral(): void
    {
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        $service = app(ReferralService::class);
        $code = $service->codeFor($this->userWithRole('parent'));
        $buyer = $this->userWithRole('parent');
        $stale = $service->attribute($buyer, $code->code, 'reversed-before-activation');
        $subscription = $this->completedLearningWithSubscription($buyer, 'premium_individual');
        $service->maybeActivate($stale->fresh());
        $service->reverseForSubscription($subscription);
        $this->assertSame('reversed', $stale->fresh()->status);

        $service->maybeActivate($stale);

        $this->assertSame('reversed', $stale->fresh()->status);
        $this->assertNull($stale->fresh()->activated_at);
        $this->assertNull($service->recordReferredPurchase($buyer, $subscription, 200_000, 'after-reversal'));
        $this->assertDatabaseCount('commissions', 0);
    }

    private function completedLearningWithSubscription(User $user, string $planCode): Subscription
    {
        $lesson = $this->publishedLesson();
        $learner = LearnerProfile::create(['user_id' => $user->id, 'display_name' => 'Referral learner']);
        $learner->lessonProgress()->create(['lesson_id' => $lesson->id, 'status' => 'completed', 'completed_at' => now()]);
        QuizAttempt::create([
            'learner_profile_id' => $learner->id,
            'quiz_id' => $lesson->components->firstWhere('type', 'quiz')->quiz->id,
            'attempt_no' => 1, 'started_at' => now(), 'completed_at' => now(),
        ]);

        return Subscription::create([
            'subscriber_type' => User::class, 'subscriber_id' => $user->id,
            'plan_id' => Plan::where('code', $planCode)->firstOrFail()->id,
            'status' => 'active', 'method' => 'card', 'started_at' => now(),
        ]);
    }
}
