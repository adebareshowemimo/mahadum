<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\TelcoOtp;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TelcoOtpTest extends TestCase
{
    use RefreshDatabase;

    private const MSISDN = '08031234567';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        $this->actingAsUser($this->userWithRole('parent'));
    }

    private function plan(): Plan
    {
        return Plan::where('code', 'premium_individual')->first();
    }

    /** Request a code, then force a known hash so we can confirm it deterministically. */
    private function requestAndPinCode(string $code = '123456'): void
    {
        $this->postJson('/api/v1/telco/otp/request', ['msisdn' => self::MSISDN, 'operator' => 'mtn'])
            ->assertStatus(202)
            ->assertJsonPath('data.msisdn', self::MSISDN);

        TelcoOtp::latest()->first()->update(['code_hash' => Hash::make($code)]);
    }

    public function test_subscribe_is_blocked_without_a_verified_otp(): void
    {
        $this->postJson('/api/v1/telco/subscribe', ['plan_id' => $this->plan()->id, 'msisdn' => self::MSISDN, 'operator' => 'mtn'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Phone number not verified. Request and confirm an OTP first.');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_full_otp_flow_enrols_the_number(): void
    {
        $this->requestAndPinCode();

        $this->postJson('/api/v1/telco/otp/verify', ['msisdn' => self::MSISDN, 'code' => '123456'])
            ->assertOk()->assertJsonPath('data.verified', true);

        $this->postJson('/api/v1/telco/subscribe', ['plan_id' => $this->plan()->id, 'msisdn' => self::MSISDN, 'operator' => 'mtn'])
            ->assertCreated()->assertJsonPath('data.state', 'active');

        $this->assertDatabaseHas('telco_subscriptions', ['msisdn' => self::MSISDN, 'state' => 'active']);
        $this->assertDatabaseHas('telco_otps', ['msisdn' => self::MSISDN]);
    }

    public function test_wrong_code_does_not_verify(): void
    {
        $this->requestAndPinCode();

        $this->postJson('/api/v1/telco/otp/verify', ['msisdn' => self::MSISDN, 'code' => '000000'])
            ->assertStatus(422);

        $this->postJson('/api/v1/telco/subscribe', ['plan_id' => $this->plan()->id, 'msisdn' => self::MSISDN, 'operator' => 'mtn'])
            ->assertStatus(403);
    }

    public function test_verified_otp_is_single_use(): void
    {
        $this->requestAndPinCode();
        $this->postJson('/api/v1/telco/otp/verify', ['msisdn' => self::MSISDN, 'code' => '123456'])->assertOk();

        $this->postJson('/api/v1/telco/subscribe', ['plan_id' => $this->plan()->id, 'msisdn' => self::MSISDN, 'operator' => 'mtn'])
            ->assertCreated();

        // The verification was consumed — a second enrolment must re-verify.
        $this->postJson('/api/v1/telco/subscribe', ['plan_id' => $this->plan()->id, 'msisdn' => self::MSISDN, 'operator' => 'mtn'])
            ->assertStatus(403);
    }
}
