<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Settings;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolReferralTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithAdmin(): array
    {
        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);

        return [$org, $admin];
    }

    public function test_school_admin_gets_the_org_own_code_and_summary(): void
    {
        $this->seedRbac();
        [$org, $admin] = $this->orgWithAdmin();
        $this->actingAsUser($admin);

        $first = $this->getJson("/api/v1/schools/{$org->id}/referrals/summary")->assertOk();
        $code = $first->json('data.code');

        $this->assertDatabaseHas('referral_codes', [
            'owner_type' => Organization::class, 'owner_id' => $org->id, 'kind' => 'org', 'code' => $code,
        ]);

        // Idempotent: same code on a second call.
        $second = $this->getJson("/api/v1/schools/{$org->id}/referrals/summary")->assertOk();
        $this->assertSame($code, $second->json('data.code'));
    }

    public function test_signup_with_org_code_qualifies_commission_to_the_organization(): void
    {
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        [$org, $admin] = $this->orgWithAdmin();
        $this->actingAsUser($admin);

        $code = $this->getJson("/api/v1/schools/{$org->id}/referrals/summary")->json('data.code');

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Ref', 'last_name' => 'Erred', 'email' => 'org-referred@test.local',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'device_name' => 'd',
            'referral_code' => $code,
        ], ['X-Device-Id' => 'dev-org-1'])->assertCreated();

        $referred = User::where('email', 'org-referred@test.local')->first();
        $this->actingAsUser($referred);
        $plan = Plan::where('code', 'premium_individual')->first();
        $subId = $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card'], [
            'Idempotency-Key' => 'org-sub-1',
        ])->json('data.subscription_id');

        app(PaymentService::class)->process('paystack', 'org-evt-1', "sub_$subId", 'success', $plan->price_minor, []);

        $this->assertDatabaseHas('commissions', [
            'beneficiary_type' => Organization::class, 'beneficiary_id' => $org->id,
            'status' => 'pending_escrow', 'amount_minor' => (int) round($plan->price_minor * 0.20),
        ]);

        $this->actingAsUser($admin);
        $this->getJson("/api/v1/schools/{$org->id}/referrals/summary")
            ->assertOk()
            ->assertJsonPath('data.commissions.pending_escrow.c', 1);
    }

    public function test_school_admin_requests_a_payout_for_the_org(): void
    {
        $this->seedRbac();
        app(Settings::class)->set(['referral.payout_floor_minor' => 100_000]);
        [$org, $admin] = $this->orgWithAdmin();
        $this->actingAsUser($admin);

        $code = $this->getJson("/api/v1/schools/{$org->id}/referrals/summary")->json('data.code');
        $referralCode = ReferralCode::where('code', $code)->firstOrFail();
        $referral = Referral::create(['referral_code_id' => $referralCode->id, 'status' => 'qualified', 'signed_up_at' => now()]);
        $commission = new Commission(['amount_minor' => 200_000, 'status' => 'cleared', 'cleared_at' => now()]);
        $commission->referral()->associate($referral);
        $commission->beneficiary()->associate($org);
        $commission->save();

        $this->postJson("/api/v1/schools/{$org->id}/referrals/payouts/request", [
            'amount_minor' => 150_000, 'method' => 'bank',
        ], ['Idempotency-Key' => 'org-payout-1'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested');

        $this->assertDatabaseHas('payouts', [
            'beneficiary_type' => Organization::class, 'beneficiary_id' => $org->id, 'amount_minor' => 150_000,
        ]);
    }

    public function test_school_admin_cannot_view_a_foreign_org_referral_summary(): void
    {
        $this->seedRbac();
        [$home, $admin] = $this->orgWithAdmin();
        $foreign = Organization::create(['name' => 'Foreign', 'type' => 'school', 'slug' => 'foreign', 'status' => 'active']);
        $this->actingAsUser($admin);

        $this->getJson("/api/v1/schools/{$foreign->id}/referrals/summary")->assertStatus(403);
    }
}
