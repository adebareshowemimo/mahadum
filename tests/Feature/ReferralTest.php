<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\LearnerProfile;
use App\Models\LessonProgress;
use App\Models\Payout;
use App\Models\Plan;
use App\Models\QuizAttempt;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFundingTransaction;
use App\Services\Billing\PaymentService;
use App\Services\Referral\ReferralService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    /** Activation needs a paid subscription AND a finished lesson AND a finished quiz. */
    public function test_activation_gate_requires_paid_sub_plus_lesson_and_quiz(): void
    {
        $this->seedRbac();
        $this->seed(PlanSeeder::class);

        $referrer = $this->userWithRole('parent');
        $code = app(ReferralService::class)->codeFor($referrer)->code;

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Ref', 'last_name' => 'Erred', 'email' => 'referred@test.local', 'phone' => '+2348012340002',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'device_name' => 'd',
            'referral_code' => $code,
        ], ['X-Device-Id' => 'dev1'])->assertCreated();

        $referred = User::where('email', 'referred@test.local')->first();
        $referral = Referral::where('referred_user_id', $referred->id)->firstOrFail();
        $this->assertSame('pending', $referral->status);

        $lesson = $this->publishedLesson();
        $quizId = $lesson->components->firstWhere('type', 'quiz')->quiz->id;
        $profile = LearnerProfile::create(['user_id' => $referred->id, 'display_name' => 'R', 'current_level' => 1]);
        $plan = Plan::where('code', 'premium_individual')->first();

        // Paid subscription alone → still pending.
        Subscription::create([
            'subscriber_type' => User::class, 'subscriber_id' => $referred->id,
            'plan_id' => $plan->id, 'status' => 'active', 'method' => 'card', 'started_at' => now(),
        ]);
        app(ReferralService::class)->maybeActivateForUser($referred);
        $this->assertSame('pending', $referral->fresh()->status);

        // + a finished lesson → still pending (no quiz yet).
        LessonProgress::create(['learner_profile_id' => $profile->id, 'lesson_id' => $lesson->id, 'status' => 'completed', 'completed_at' => now()]);
        app(ReferralService::class)->maybeActivateForUser($referred);
        $this->assertSame('pending', $referral->fresh()->status);

        // + a finished quiz → activates.
        QuizAttempt::create(['learner_profile_id' => $profile->id, 'quiz_id' => $quizId, 'attempt_no' => 1, 'started_at' => now(), 'completed_at' => now()]);
        app(ReferralService::class)->maybeActivateForUser($referred);

        $referral->refresh();
        $this->assertSame('qualified', $referral->status);
        $this->assertNotNull($referral->activated_at);
    }

    /**
     * @return array{0: Referral, 1: User, 2: User} [referral, referrer, referred] with an active referral
     */
    private function activatedReferral(string $email = 'buyer@test.local'): array
    {
        $this->seed(PlanSeeder::class);

        $referrer = $this->userWithRole('parent');
        $referred = $this->userWithRole('parent', ['email' => $email]);
        $code = app(ReferralService::class)->codeFor($referrer);

        $referral = Referral::create([
            'referral_code_id' => $code->id, 'referred_user_id' => $referred->id,
            'status' => 'qualified', 'signed_up_at' => now()->subDays(1), 'activated_at' => now()->subDays(1),
        ]);

        return [$referral, $referrer, $referred];
    }

    private function fundWallet(User $user, int $amountMinor, string $eventKey): void
    {
        $wallet = Wallet::create(['owner_type' => User::class, 'owner_id' => $user->id, 'currency' => 'NGN']);
        $funding = WalletFundingTransaction::create([
            'wallet_id' => $wallet->id, 'gateway' => 'paystack', 'amount_minor' => $amountMinor,
            'currency' => 'NGN', 'status' => 'pending', 'gateway_ref' => 'wf-'.$eventKey,
        ]);
        app(PaymentService::class)->process('paystack', $eventKey, $funding->gateway_ref, 'success', $amountMinor, []);
    }

    public function test_purchase_within_window_earns_five_percent_and_after_window_earns_nothing(): void
    {
        $this->seedRbac();
        [$referral, $referrer, $referred] = $this->activatedReferral();

        $this->fundWallet($referred, 200_000, 'evt-in');

        $this->assertDatabaseHas('commissions', [
            'referral_id' => $referral->id, 'beneficiary_type' => User::class, 'beneficiary_id' => $referrer->id,
            'status' => 'pending_escrow', 'kind' => 'purchase', 'amount_minor' => 10_000, // 5% of 200000
        ]);

        // Past the 30-day earning window → no further commission.
        $referral->update(['activated_at' => now()->subDays(40)]);
        $this->fundWallet($referred, 200_000, 'evt-out');

        $this->assertSame(1, Commission::where('referral_id', $referral->id)->count());
    }

    public function test_commission_rate_follows_the_admin_setting(): void
    {
        $this->seedRbac();
        $admin = $this->userWithRole('super_admin');
        [$referral, $referrer, $referred] = $this->activatedReferral();

        $this->actingAsUser($admin);
        $this->putJson('/api/v1/admin/settings', [
            'values' => ['referral.commission_bps' => 1000],
        ])->assertOk();

        $this->fundWallet($referred, 200_000, 'evt-x');

        $this->assertDatabaseHas('commissions', [
            'referral_id' => $referral->id, 'amount_minor' => 20_000, // 10% of 200000
        ]);
    }

    public function test_refund_of_a_purchase_unwinds_its_escrowed_commission(): void
    {
        $this->seedRbac();
        [$referral, , $referred] = $this->activatedReferral();

        $wallet = Wallet::create(['owner_type' => User::class, 'owner_id' => $referred->id, 'currency' => 'NGN']);
        $funding = WalletFundingTransaction::create([
            'wallet_id' => $wallet->id, 'gateway' => 'paystack', 'amount_minor' => 200_000,
            'currency' => 'NGN', 'status' => 'pending', 'gateway_ref' => 'wf-refund',
        ]);
        $payments = app(PaymentService::class);
        $payments->process('paystack', 'pay-r', $funding->gateway_ref, 'success', 200_000, []);

        $commission = Commission::where('referral_id', $referral->id)->firstOrFail();
        $this->assertSame('pending_escrow', $commission->status);

        $payments->process('paystack', 'refund-r', $funding->gateway_ref, 'refund', 200_000, []);
        $this->assertSame('reversed', $commission->fresh()->status);
    }

    public function test_clawback_flagged_when_a_refund_hits_an_already_cleared_commission(): void
    {
        $this->seedRbac();
        [$referral, , $referred] = $this->activatedReferral();
        $this->fundWallet($referred, 200_000, 'evt-cb');

        $commission = Commission::where('referral_id', $referral->id)->firstOrFail();
        $commission->update(['status' => 'cleared', 'cleared_at' => now()]);

        app(ReferralService::class)->reverseForSource($commission->source_type, $commission->source_id);
        $this->assertSame('clawback_pending', $commission->fresh()->status);
    }

    public function test_same_device_signup_is_rejected(): void
    {
        $this->seedRbac();
        $referrer = $this->userWithRole('parent');
        $code = app(ReferralService::class)->codeFor($referrer)->code;

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'A', 'last_name' => 'One', 'email' => 'a@test.local', 'phone' => '+2348012340003',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'device_name' => 'd', 'referral_code' => $code,
        ], ['X-Device-Id' => 'dev9'])->assertCreated();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'B', 'last_name' => 'Two', 'email' => 'b@test.local', 'phone' => '+2348012340004',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'device_name' => 'd', 'referral_code' => $code,
        ], ['X-Device-Id' => 'dev9'])->assertCreated();

        $this->assertEquals(1, Referral::where('status', 'rejected')->count());
    }

    public function test_clear_escrow_and_velocity_flag_commands(): void
    {
        $this->seedRbac();
        $referrer = $this->userWithRole('parent');
        $referred = $this->userWithRole('parent', ['email' => 'r2@test.local']);
        $code = app(ReferralService::class)->codeFor($referrer);

        $referral = Referral::create(['referral_code_id' => $code->id, 'referred_user_id' => $referred->id, 'status' => 'qualified', 'signed_up_at' => now()]);
        $commission = new Commission(['amount_minor' => 30000, 'status' => 'pending_escrow', 'escrow_until' => now()->subDay()]);
        $commission->referral()->associate($referral);
        $commission->beneficiary()->associate($referrer);
        $commission->save();

        Artisan::call('commissions:clear-escrow');
        $this->assertEquals('cleared', $commission->fresh()->status);

        for ($i = 0; $i < 16; $i++) {
            Referral::create(['referral_code_id' => $code->id, 'status' => 'pending', 'signed_up_at' => now()]);
        }
        Artisan::call('referrals:flag-velocity');
        $this->assertEquals('flagged', ReferralCode::find($code->id)->status);
    }

    public function test_summary_reports_cleared_balance_net_of_committed_payouts(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('parent');
        $code = app(ReferralService::class)->codeFor($user);
        $referral = Referral::create([
            'referral_code_id' => $code->id,
            'status' => 'qualified',
            'signed_up_at' => now(),
        ]);
        $commission = new Commission(['amount_minor' => 500_000, 'status' => 'cleared']);
        $commission->referral()->associate($referral);
        $commission->beneficiary()->associate($user);
        $commission->save();

        $payout = new Payout([
            'amount_minor' => 125_000,
            'method' => 'bank',
            'status' => 'requested',
            'requested_at' => now(),
        ]);
        $payout->beneficiary()->associate($user);
        $payout->save();

        $this->actingAsUser($user);
        $this->getJson('/api/v1/referrals/summary')
            ->assertOk()
            ->assertJsonPath('data.available_minor', 375_000);
    }
}
