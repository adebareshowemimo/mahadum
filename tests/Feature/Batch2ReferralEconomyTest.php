<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\LessonProgress;
use App\Models\Payout;
use App\Models\Plan;
use App\Models\QuizAttempt;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Referral\ReferralService;
use App\Services\Settings;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class Batch2ReferralEconomyTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function referralFixture(): array
    {
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        Notification::fake();
        app(Settings::class)->set(['referral.commission_bps' => 500, 'referral.earning_window_days' => 30, 'referral.escrow_days' => 14, 'referral.payout_floor_minor' => 500_000, 'referral.payout_cap_minor' => 5_000_000]);
        $referrer = $this->userWithRole('parent');
        $buyer = $this->userWithRole('parent');
        $child = $this->parentWithChild($buyer);
        $service = app(ReferralService::class);
        $referral = $service->attribute($buyer, $service->codeFor($referrer)->code, 'batch-two-device');
        $subscription = Subscription::create(['subscriber_type' => User::class, 'subscriber_id' => $buyer->id, 'plan_id' => Plan::where('code', 'premium_individual')->firstOrFail()->id, 'status' => 'pending', 'method' => 'card']);
        $lesson = $this->publishedLesson();
        LessonProgress::create(['learner_profile_id' => $child->id, 'lesson_id' => $lesson->id, 'status' => 'completed', 'completed_at' => now()]);
        QuizAttempt::create(['learner_profile_id' => $child->id, 'quiz_id' => $lesson->components->firstWhere('type', 'quiz')->quiz->id, 'attempt_no' => 1, 'started_at' => now(), 'completed_at' => now()]);
        $service->maybeActivateForUser($buyer);
        $this->assertSame('pending', $referral->fresh()->status);

        return [$referral, $referrer, $buyer, $subscription];
    }

    public function test_confirmed_subscription_activates_household_and_posts_exact_five_percent_once(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$referral, $referrer, $buyer, $subscription] = $this->referralFixture();
        $payments = app(PaymentService::class);
        $this->assertSame('subscription_active', $payments->process('paystack', 'batch-two-charge', "sub_$subscription->id", 'success', 100_001, []));
        $this->assertSame('qualified', $referral->fresh()->status);
        $this->assertSame('duplicate', $payments->process('paystack', 'batch-two-charge', "sub_$subscription->id", 'success', 100_001, []));
        $commission = Commission::where('referral_id', $referral->id)->sole();
        $this->assertSame(5_000, $commission->amount_minor);
        $this->assertSame($referrer->id, $commission->beneficiary_id);
        $this->assertTrue($commission->escrow_until->equalTo(now()->addDays(14)));
        $this->actingAsUser($referrer);
        $this->getJson('/api/v1/referrals/summary')->assertJsonPath('data.commissions.pending_escrow.total', 5_000)->assertJsonPath('data.available_minor', 0);
        $this->travel(30)->days();
        $payments->process('paystack', 'day-thirty', "sub_$subscription->id", 'success', 100_001, []);
        $this->assertDatabaseCount('commissions', 2);
        $this->travel(1)->seconds();
        $payments->process('paystack', 'after-window', "sub_$subscription->id", 'success', 100_001, []);
        $this->assertDatabaseCount('commissions', 2);
    }

    public function test_payment_before_learning_activates_later_without_backdating_a_purchase_commission(): void
    {
        [$referral, , $buyer, $subscription] = $this->referralFixture();
        LessonProgress::query()->update(['status' => 'in_progress', 'completed_at' => null]);
        QuizAttempt::query()->update(['completed_at' => null]);
        app(PaymentService::class)->process('paystack', 'paid-before-learning', "sub_$subscription->id", 'success', 100_000, []);
        $this->assertSame('pending', $referral->fresh()->status);
        $this->assertDatabaseCount('commissions', 0);
        LessonProgress::query()->update(['status' => 'completed', 'completed_at' => now()]);
        QuizAttempt::query()->update(['completed_at' => now()]);
        app(ReferralService::class)->maybeActivateForUser($buyer);
        $this->assertSame('qualified', $referral->fresh()->status);
        $this->assertDatabaseCount('commissions', 0);
    }

    public function test_escrow_waits_fourteen_days_and_refund_replays_do_not_restore_reward(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$referral, , , $subscription] = $this->referralFixture();
        $payments = app(PaymentService::class);
        $payments->process('paystack', 'escrow-paid', "sub_$subscription->id", 'success', 100_000, []);
        $commission = Commission::where('referral_id', $referral->id)->sole();
        $this->travel(14 * 24 * 3600 - 1)->seconds();
        $this->artisan('commissions:clear-escrow')->assertSuccessful();
        $this->assertSame('pending_escrow', $commission->fresh()->status);
        $this->travel(1)->seconds();
        $this->artisan('commissions:clear-escrow')->assertSuccessful();
        $this->assertSame('cleared', $commission->fresh()->status);
        $payments->process('paystack', 'escrow-refund', "sub_$subscription->id", 'refund', 100_000, []);
        $this->assertSame('clawback_pending', $commission->fresh()->status);
        $this->assertSame('reversed', $referral->fresh()->status);
        $this->assertSame('duplicate', $payments->process('paystack', 'escrow-refund', "sub_$subscription->id", 'refund', 100_000, []));
        $this->assertDatabaseCount('commissions', 1);
    }

    public function test_payout_floor_cap_replay_and_month_reset_use_minor_units(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(5));
        [$referral, $referrer] = $this->referralFixture();
        $commission = new Commission(['amount_minor' => 12_000_000, 'status' => 'cleared', 'cleared_at' => now()]);
        $commission->referral()->associate($referral);
        $commission->beneficiary()->associate($referrer);
        $commission->save();
        $this->actingAsUser($referrer);
        $url = '/api/v1/payouts/request';
        $this->postJson($url, ['amount_minor' => 499_999, 'method' => 'bank'], ['Idempotency-Key' => 'below-floor'])->assertUnprocessable();
        $payload = ['amount_minor' => 5_000_000, 'method' => 'bank'];
        $id = $this->postJson($url, $payload, ['Idempotency-Key' => 'at-cap'])->assertCreated()->json('data.id');
        $this->postJson($url, $payload, ['Idempotency-Key' => 'at-cap'])->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('payouts', 1);
        $this->postJson($url, ['amount_minor' => 500_000, 'method' => 'bank'], ['Idempotency-Key' => 'over-cap'])->assertUnprocessable()->assertJsonPath('error.code', 'payout_cap_exceeded');
        $this->getJson('/api/v1/referrals/summary')->assertJsonPath('data.available_minor', 7_000_000);
        $this->travelTo(now()->addMonth()->startOfMonth());
        $this->postJson($url, ['amount_minor' => 500_000, 'method' => 'bank'], ['Idempotency-Key' => 'new-month'])->assertCreated();
        $this->assertSame(5_500_000, (int) Payout::sum('amount_minor'));
    }
}
