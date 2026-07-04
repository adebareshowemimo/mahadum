<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Referral\ReferralService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_attributes_and_verified_payment_creates_escrow_commission(): void
    {
        $this->seedRbac();
        $this->seed(PlanSeeder::class);

        $referrer = $this->userWithRole('parent');
        $code = app(ReferralService::class)->codeFor($referrer)->code;

        // referred signs up with the code
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Ref', 'last_name' => 'Erred', 'email' => 'referred@test.local',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'device_name' => 'd',
            'referral_code' => $code,
        ], ['X-Device-Id' => 'dev1'])->assertCreated();

        $referred = User::where('email', 'referred@test.local')->first();
        $this->assertDatabaseHas('referrals', ['referred_user_id' => $referred->id, 'status' => 'pending']);

        // referred subscribes (card) then the gateway confirms → qualify
        $this->actingAsUser($referred);
        $plan = Plan::where('code', 'premium_individual')->first();
        $subId = $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card'], [
            'Idempotency-Key' => 's-1',
        ])->json('data.subscription_id');

        app(PaymentService::class)->process('paystack', 'evt-1', "sub_$subId", 'success', $plan->price_minor, []);

        $this->assertDatabaseHas('referrals', ['referred_user_id' => $referred->id, 'status' => 'qualified']);
        $this->assertDatabaseHas('commissions', [
            'beneficiary_type' => User::class, 'beneficiary_id' => $referrer->id,
            'status' => 'pending_escrow', 'amount_minor' => (int) round($plan->price_minor * 0.20),
        ]);
    }

    /**
     * Drives a referred user to a qualified subscription with a pending_escrow
     * commission, and returns [referral, subscriptionId, PaymentService].
     *
     * @return array{0: Referral, 1: int, 2: PaymentService}
     */
    private function qualifiedReferral(string $email): array
    {
        $this->seed(PlanSeeder::class);

        $referrer = $this->userWithRole('parent');
        $referred = $this->userWithRole('parent', ['email' => $email]);
        $code = app(ReferralService::class)->codeFor($referrer);

        $referral = Referral::create([
            'referral_code_id' => $code->id, 'referred_user_id' => $referred->id,
            'status' => 'pending', 'signed_up_at' => now(),
        ]);

        $this->actingAsUser($referred);
        $plan = Plan::where('code', 'premium_individual')->first();
        $subId = $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card'], [
            'Idempotency-Key' => 'sub-'.$email,
        ])->json('data.subscription_id');

        $payments = app(PaymentService::class);
        $payments->process('paystack', 'pay-'.$email, "sub_$subId", 'success', $plan->price_minor, []);

        return [$referral, (int) $subId, $payments];
    }

    public function test_refund_reverses_escrowed_commission_and_blocks_clearing(): void
    {
        $this->seedRbac();
        [$referral, $subId, $payments] = $this->qualifiedReferral('referred-r@test.local');

        $commission = Commission::where('referral_id', $referral->id)->firstOrFail();
        $this->assertEquals('pending_escrow', $commission->status);

        // refund the subscription → unwind the commission
        $payments->process('paystack', 'refund-r', "sub_$subId", 'refund', 100000, []);

        $this->assertEquals('reversed', $commission->fresh()->status);
        $this->assertEquals('reversed', $referral->fresh()->status);
        $this->assertEquals('refunded', Subscription::find($subId)->status);

        // a reversed commission is no longer pending_escrow, so the clearing job skips it
        $commission->update(['escrow_until' => now()->subDay()]);
        Artisan::call('commissions:clear-escrow');
        $this->assertEquals('reversed', $commission->fresh()->status);
    }

    public function test_refund_of_cleared_commission_flags_clawback(): void
    {
        $this->seedRbac();
        [$referral, $subId, $payments] = $this->qualifiedReferral('referred-c@test.local');

        $commission = Commission::where('referral_id', $referral->id)->firstOrFail();
        $commission->update(['status' => 'cleared', 'cleared_at' => now()]);

        $payments->process('paystack', 'refund-c', "sub_$subId", 'refund', 100000, []);

        $this->assertEquals('clawback_pending', $commission->fresh()->status);
    }

    public function test_same_device_signup_is_rejected(): void
    {
        $this->seedRbac();
        $referrer = $this->userWithRole('parent');
        $code = app(ReferralService::class)->codeFor($referrer)->code;

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'A', 'last_name' => 'One', 'email' => 'a@test.local',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'device_name' => 'd', 'referral_code' => $code,
        ], ['X-Device-Id' => 'dev9'])->assertCreated();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'B', 'last_name' => 'Two', 'email' => 'b@test.local',
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

        // velocity: >15 signups in 24h flags the code
        for ($i = 0; $i < 16; $i++) {
            Referral::create(['referral_code_id' => $code->id, 'status' => 'pending', 'signed_up_at' => now()]);
        }
        Artisan::call('referrals:flag-velocity');
        $this->assertEquals('flagged', ReferralCode::find($code->id)->status);
    }
}
