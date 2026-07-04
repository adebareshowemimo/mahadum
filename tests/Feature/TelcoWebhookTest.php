<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TelcoBillingAttempt;
use App\Models\TelcoSubscription;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TelcoWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.telco.webhook_secret', 'telcosecret');
    }

    private function dlr(array $payload, ?string $secret = 'telcosecret'): TestResponse
    {
        $body = json_encode($payload);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if ($secret !== null) {
            $headers['HTTP_X_TELCO_SIGNATURE'] = hash_hmac('sha256', $body, $secret);
        }

        return $this->call('POST', '/api/v1/webhooks/telco/dlr', [], [], [], $headers, $body);
    }

    private function pendingAttempt(string $operatorRef): TelcoBillingAttempt
    {
        $plan = Plan::create(['code' => 'p', 'name' => 'P', 'price_minor' => 30000, 'currency' => 'NGN', 'interval' => 'month', 'max_profiles' => 1]);
        $sub = new Subscription(['plan_id' => $plan->id, 'method' => 'airtime', 'status' => 'active', 'started_at' => now(), 'renews_at' => now()->addMonth()]);
        $sub->subscriber()->associate(User::factory()->create());
        $sub->save();

        $telco = TelcoSubscription::create([
            'subscription_id' => $sub->id, 'msisdn' => '08031234567', 'operator' => 'mtn',
            'daily_amount_minor' => 1000, 'state' => 'grace', 'grace_until' => now()->addDay(),
        ]);

        return TelcoBillingAttempt::create([
            'telco_subscription_id' => $telco->id, 'attempted_at' => now(),
            'amount_minor' => 1000, 'operator_ref' => $operatorRef,
        ]);
    }

    public function test_unsigned_or_badly_signed_dlr_is_rejected(): void
    {
        $this->dlr(['operator_ref' => 'op-1', 'result' => 'success'], secret: null)->assertStatus(401);
        $this->dlr(['operator_ref' => 'op-1', 'result' => 'success'], secret: 'wrong')->assertStatus(401);
    }

    public function test_signed_success_marks_attempt_and_reactivates_subscription(): void
    {
        $attempt = $this->pendingAttempt('op-2');

        $this->dlr(['operator_ref' => 'op-2', 'result' => 'success'])
            ->assertOk()->assertJsonPath('status', 'ok');

        $this->assertEquals('success', $attempt->fresh()->result);
        $this->assertDatabaseHas('telco_subscriptions', [
            'id' => $attempt->telco_subscription_id, 'state' => 'active', 'grace_until' => null,
        ]);
    }

    public function test_redelivered_dlr_is_idempotent(): void
    {
        $attempt = $this->pendingAttempt('op-3');
        $payload = ['operator_ref' => 'op-3', 'result' => 'success'];

        $this->dlr($payload)->assertOk()->assertJsonPath('status', 'ok');
        $firstNextAttempt = $attempt->fresh()->telcoSubscription->next_attempt_at;

        // replay → duplicate, schedule not advanced again
        $this->dlr($payload)->assertOk()->assertJsonPath('status', 'duplicate');
        $this->assertEquals(
            $firstNextAttempt,
            $attempt->fresh()->telcoSubscription->next_attempt_at,
        );

        $this->assertEquals(1, WebhookEvent::where('source', 'telco')->where('event', 'op-3')->count());
    }
}
