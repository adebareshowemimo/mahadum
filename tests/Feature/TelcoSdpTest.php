<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TelcoSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelcoSdpTest extends TestCase
{
    use RefreshDatabase;

    private function goLive(): void
    {
        config([
            'services.telco.live' => true,
            'services.telco.base_url' => 'https://sdp.test',
            'services.telco.token' => 'sdp_token',
        ]);
    }

    private function dueTelcoSub(string $state = 'active'): TelcoSubscription
    {
        $plan = Plan::create(['code' => 'airtime-plan', 'name' => 'Airtime', 'price_minor' => 30000, 'currency' => 'NGN', 'interval' => 'month', 'max_profiles' => 1]);
        $sub = new Subscription(['plan_id' => $plan->id, 'method' => 'airtime', 'status' => 'active', 'started_at' => now(), 'renews_at' => now()->addMonth()]);
        $sub->subscriber()->associate(User::factory()->create());
        $sub->save();

        return TelcoSubscription::create([
            'subscription_id' => $sub->id, 'msisdn' => '08031234567', 'operator' => 'mtn',
            'daily_amount_minor' => 1000, 'state' => $state, 'next_attempt_at' => null,
        ]);
    }

    public function test_daily_billing_charges_via_sdp_when_live(): void
    {
        $this->goLive();
        Http::fake(['sdp.test/*' => Http::response(['status' => 'success', 'operator_ref' => 'op-xyz'])]);

        $telco = $this->dueTelcoSub();
        Artisan::call('telco:bill-daily');

        $this->assertDatabaseHas('telco_billing_attempts', [
            'telco_subscription_id' => $telco->id, 'result' => 'success', 'operator_ref' => 'op-xyz',
        ]);
        $this->assertDatabaseHas('telco_subscriptions', ['id' => $telco->id, 'state' => 'active']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/charge')
            && $request['msisdn'] === '08031234567'
            && $request['amount'] === 1000
            && $request->hasHeader('Authorization', 'Bearer sdp_token'));
    }

    public function test_insufficient_charge_drops_subscription_to_grace(): void
    {
        $this->goLive();
        Http::fake(['sdp.test/*' => Http::response(['status' => 'insufficient'])]);

        $telco = $this->dueTelcoSub('active');
        Artisan::call('telco:bill-daily');

        $this->assertDatabaseHas('telco_subscriptions', ['id' => $telco->id, 'state' => 'grace']);
        $this->assertDatabaseHas('telco_billing_attempts', ['telco_subscription_id' => $telco->id, 'result' => 'insufficient']);
    }

    public function test_otp_request_sends_sms_via_sdp_when_live(): void
    {
        $this->goLive();
        Http::fake(['sdp.test/*' => Http::response(['queued' => true])]);

        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));

        $this->postJson('/api/v1/telco/otp/request', ['msisdn' => '08031234567', 'operator' => 'mtn'])
            ->assertStatus(202);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/otp') && $request['msisdn'] === '08031234567');
    }

    public function test_off_live_billing_succeeds_without_http(): void
    {
        Http::fake(); // live defaults to false → NullTelcoGateway

        $telco = $this->dueTelcoSub();
        Artisan::call('telco:bill-daily');

        $this->assertDatabaseHas('telco_billing_attempts', ['telco_subscription_id' => $telco->id, 'result' => 'success']);
        Http::assertNothingSent();
    }
}
