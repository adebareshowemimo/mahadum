<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\WalletFundingTransaction;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class BillingWebhookTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.paystack.secret', 'testsecret');
        config()->set('services.monnify.secret', 'mnfysecret');
    }

    private function paystack(array $payload): TestResponse
    {
        $body = json_encode($payload);
        $sig = hash_hmac('sha512', $body, 'testsecret');

        return $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }

    private function monnify(array $payload): TestResponse
    {
        $body = json_encode($payload);
        $sig = hash_hmac('sha512', $body, 'mnfysecret');

        return $this->call('POST', '/api/v1/webhooks/monnify', [], [], [], [
            'HTTP_MONNIFY_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => 'nope', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['event' => 'charge.success', 'data' => []]))->assertStatus(401);
    }

    public function test_funding_webhook_credits_wallet_and_is_idempotent(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);

        $ref = $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'paystack'], [
            'Idempotency-Key' => 'f-1',
        ])->json('data.gateway_ref');

        $payload = ['event' => 'charge.success', 'data' => ['id' => 1, 'reference' => $ref, 'status' => 'success', 'amount' => 50000]];

        $this->paystack($payload)->assertOk()->assertJsonPath('status', 'funded');
        $this->getJson('/api/v1/wallet')->assertJsonPath('data.currency_minor', 50000);

        // replay → duplicate, no double credit
        $this->paystack($payload)->assertOk()->assertJsonPath('status', 'duplicate');
        $this->getJson('/api/v1/wallet')->assertJsonPath('data.currency_minor', 50000);

        $this->assertDatabaseHas('wallet_funding_transactions', ['gateway_ref' => $ref, 'status' => 'success']);
    }

    public function test_monnify_invalid_signature_is_rejected(): void
    {
        $this->call('POST', '/api/v1/webhooks/monnify', [], [], [], [
            'HTTP_MONNIFY_SIGNATURE' => 'nope', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['eventType' => 'SUCCESSFUL_TRANSACTION', 'eventData' => []]))->assertStatus(401);
    }

    public function test_monnify_funding_webhook_credits_wallet_and_is_idempotent(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);

        $ref = $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'monnify'], [
            'Idempotency-Key' => 'mnfy-f-1',
        ])->json('data.gateway_ref');

        // Monnify reports amountPaid in major units (Naira) as a string.
        $payload = ['eventType' => 'SUCCESSFUL_TRANSACTION', 'eventData' => [
            'paymentReference' => $ref, 'transactionReference' => 'MNFY|999', 'paymentStatus' => 'PAID', 'amountPaid' => '500.00',
        ]];

        $this->monnify($payload)->assertOk()->assertJsonPath('status', 'funded');
        $this->getJson('/api/v1/wallet')->assertJsonPath('data.currency_minor', 50000);

        // replay → duplicate, no double credit
        $this->monnify($payload)->assertOk()->assertJsonPath('status', 'duplicate');
        $this->getJson('/api/v1/wallet')->assertJsonPath('data.currency_minor', 50000);

        $this->assertDatabaseHas('wallet_funding_transactions', ['gateway_ref' => $ref, 'status' => 'success']);
    }

    public function test_monnify_refund_reverses_funding_via_gateway_txn_ref(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);

        $ref = $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'monnify'], [
            'Idempotency-Key' => 'mnfy-rf-1',
        ])->json('data.gateway_ref');

        // Live init would have stored Monnify's own transaction id; simulate that
        // (NullGateway leaves it null locally).
        $txnRef = 'MNFY|20190816083102|000021';
        WalletFundingTransaction::where('gateway_ref', $ref)->update(['gateway_txn_ref' => $txnRef]);

        $this->monnify(['eventType' => 'SUCCESSFUL_TRANSACTION', 'eventData' => [
            'paymentReference' => $ref, 'transactionReference' => $txnRef, 'paymentStatus' => 'PAID', 'amountPaid' => '500.00',
        ]])->assertOk()->assertJsonPath('status', 'funded');
        $this->getJson('/api/v1/wallet')->assertJsonPath('data.currency_minor', 50000);

        // The refund webhook carries only Monnify's transactionReference (not our ref).
        $refund = ['eventType' => 'SUCCESSFUL_REFUND', 'eventData' => [
            'transactionReference' => $txnRef, 'refundReference' => 'rf001', 'refundStatus' => 'COMPLETED', 'refundAmount' => 500.00,
        ]];

        $this->monnify($refund)->assertOk()->assertJsonPath('status', 'reversed');
        $this->getJson('/api/v1/wallet')->assertJsonPath('data.currency_minor', 0);
        $this->assertDatabaseHas('wallet_funding_transactions', ['gateway_ref' => $ref, 'status' => 'refunded']);

        // replay → duplicate, no double debit
        $this->monnify($refund)->assertOk()->assertJsonPath('status', 'duplicate');
        $this->getJson('/api/v1/wallet')->assertJsonPath('data.currency_minor', 0);
    }

    public function test_subscription_webhook_activates(): void
    {
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $plan = Plan::where('code', 'premium_individual')->first();

        $subId = $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card'], [
            'Idempotency-Key' => 's-1',
        ])->assertCreated()->json('data.subscription_id');

        $this->paystack(['event' => 'charge.success', 'data' => ['id' => 2, 'reference' => "sub_$subId", 'status' => 'success', 'amount' => $plan->price_minor]])
            ->assertOk()->assertJsonPath('status', 'subscription_active');

        $this->assertEquals('active', Subscription::find($subId)->status);
    }

    public function test_refund_reverses_wallet_funding_and_is_idempotent(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);

        $ref = $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'paystack'], [
            'Idempotency-Key' => 'rf-1',
        ])->json('data.gateway_ref');

        $this->paystack(['event' => 'charge.success', 'data' => ['id' => 10, 'reference' => $ref, 'status' => 'success', 'amount' => 50000]])
            ->assertOk()->assertJsonPath('status', 'funded');
        $this->getJson('/api/v1/wallet')->assertJsonPath('data.currency_minor', 50000);

        // refund.processed carries transaction_reference + its own id.
        $refund = ['event' => 'refund.processed', 'data' => ['id' => 11, 'transaction_reference' => $ref, 'amount' => 50000]];

        $this->paystack($refund)->assertOk()->assertJsonPath('status', 'reversed');
        $this->getJson('/api/v1/wallet')->assertJsonPath('data.currency_minor', 0);
        $this->assertDatabaseHas('wallet_funding_transactions', ['gateway_ref' => $ref, 'status' => 'refunded']);

        // replay → duplicate, no double debit (and balance stays clamped at 0)
        $this->paystack($refund)->assertOk()->assertJsonPath('status', 'duplicate');
        $this->getJson('/api/v1/wallet')->assertJsonPath('data.currency_minor', 0);
    }

    public function test_refund_cancels_subscription(): void
    {
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        $this->actingAsUser($this->userWithRole('parent'));
        $plan = Plan::where('code', 'premium_individual')->first();

        $subId = $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card'], [
            'Idempotency-Key' => 's-r',
        ])->json('data.subscription_id');

        $this->paystack(['event' => 'charge.success', 'data' => ['id' => 20, 'reference' => "sub_$subId", 'status' => 'success', 'amount' => $plan->price_minor]])->assertOk();
        $this->assertEquals('active', Subscription::find($subId)->status);

        $this->paystack(['event' => 'refund.processed', 'data' => ['id' => 21, 'transaction_reference' => "sub_$subId", 'amount' => $plan->price_minor]])
            ->assertOk()->assertJsonPath('status', 'reversed');

        $this->assertEquals('refunded', Subscription::find($subId)->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.refunded', 'subject_id' => $subId]);
    }

    public function test_monnify_subscription_refund_cancels_via_gateway_txn_ref(): void
    {
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        $this->actingAsUser($this->userWithRole('parent'));
        $plan = Plan::where('code', 'premium_individual')->first();

        $subId = $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card'], [
            'Idempotency-Key' => 'mnfy-s-r',
        ])->json('data.subscription_id');

        // Live init would have stored Monnify's transaction id; simulate it.
        $txnRef = 'MNFY|20190816083102|000099';
        Subscription::whereKey($subId)->update(['gateway_txn_ref' => $txnRef]);

        // Activate via the success webhook (which echoes our sub_ reference).
        $this->monnify(['eventType' => 'SUCCESSFUL_TRANSACTION', 'eventData' => [
            'paymentReference' => "sub_$subId", 'transactionReference' => $txnRef, 'paymentStatus' => 'PAID',
            'amountPaid' => (string) round($plan->price_minor / 100, 2),
        ]])->assertOk()->assertJsonPath('status', 'subscription_active');
        $this->assertEquals('active', Subscription::find($subId)->status);

        // The refund webhook carries only Monnify's transactionReference (not sub_<id>).
        $this->monnify(['eventType' => 'SUCCESSFUL_REFUND', 'eventData' => [
            'transactionReference' => $txnRef, 'refundReference' => 'rf-sub', 'refundStatus' => 'COMPLETED',
            'refundAmount' => round($plan->price_minor / 100, 2),
        ]])->assertOk()->assertJsonPath('status', 'reversed');

        $this->assertEquals('refunded', Subscription::find($subId)->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.refunded', 'subject_id' => $subId]);
    }

    public function test_unrecognised_event_is_recorded_but_moves_no_money(): void
    {
        $this->paystack(['event' => 'charge.dispute.create', 'data' => ['id' => 30, 'reference' => 'whatever']])
            ->assertOk()->assertJsonPath('status', 'ignored');

        $this->assertDatabaseHas('webhook_events', ['source' => 'paystack', 'event' => '30', 'status' => 'ignored']);
    }
}
