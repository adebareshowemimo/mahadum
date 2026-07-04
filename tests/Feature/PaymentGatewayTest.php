<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function goLive(string $default = 'monnify'): void
    {
        config([
            'services.payments.live' => true,
            'services.payments.default' => $default,
            'services.paystack.secret' => 'sk_test',
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.flutterwave.secret' => 'flw_test',
            'services.flutterwave.base_url' => 'https://api.flutterwave.com/v3',
            'services.monnify.api_key' => 'mk_test',
            'services.monnify.secret' => 'mnfy_secret',
            'services.monnify.contract_code' => 'CONTRACT_1',
            'services.monnify.base_url' => 'https://sandbox.monnify.com',
        ]);
    }

    public function test_wallet_fund_opens_paystack_checkout_when_live(): void
    {
        $this->goLive('paystack');
        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'reference' => 'ref'],
        ])]);

        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);

        $ref = $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'paystack'], ['Idempotency-Key' => 'gw-1'])
            ->assertCreated()
            ->assertJsonPath('data.checkout_url', 'https://checkout.paystack.com/xyz')
            ->json('data.gateway_ref');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/transaction/initialize')
            && $request['amount'] === 50000           // kobo (minor units)
            && $request['reference'] === $ref
            && $request->hasHeader('Authorization', 'Bearer sk_test'));
    }

    public function test_subscription_card_opens_checkout_when_live(): void
    {
        $this->goLive('paystack');
        Http::fake(['api.paystack.co/*' => Http::response(['data' => ['authorization_url' => 'https://checkout.paystack.com/sub']])]);

        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        $this->actingAsUser($this->userWithRole('parent'));
        $plan = Plan::where('code', 'premium_individual')->first();

        $ref = $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card'], ['Idempotency-Key' => 'gw-sub'])
            ->assertCreated()
            ->assertJsonPath('data.checkout_url', 'https://checkout.paystack.com/sub')
            ->json('data.payment_reference');

        Http::assertSent(fn ($request) => $request['amount'] === $plan->price_minor && $request['reference'] === $ref);
    }

    public function test_flutterwave_checkout_when_live(): void
    {
        $this->goLive('flutterwave');
        Http::fake(['api.flutterwave.com/*' => Http::response(['data' => ['link' => 'https://checkout.flutterwave.com/abc']])]);

        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);

        $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'flutterwave'], ['Idempotency-Key' => 'gw-flw'])
            ->assertCreated()
            ->assertJsonPath('data.checkout_url', 'https://checkout.flutterwave.com/abc');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/payments')
            && $request['amount'] === 500.0           // major units
            && is_string($request['tx_ref']));
    }

    public function test_monnify_checkout_authenticates_then_initialises_when_live(): void
    {
        $this->goLive('monnify');
        Http::fake([
            'sandbox.monnify.com/api/v1/auth/login' => Http::response(['responseBody' => ['accessToken' => 'tok_123']]),
            'sandbox.monnify.com/api/v1/merchant/transactions/init-transaction' => Http::response([
                'responseBody' => ['checkoutUrl' => 'https://sandbox.sdk.monnify.com/checkout/abc', 'transactionReference' => 'MNFY|123'],
            ]),
        ]);

        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);

        $ref = $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'monnify'], ['Idempotency-Key' => 'gw-mnfy'])
            ->assertCreated()
            ->assertJsonPath('data.checkout_url', 'https://sandbox.sdk.monnify.com/checkout/abc')
            ->json('data.gateway_ref');

        // Step 1: Basic-auth login with the api key + secret.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/auth/login')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('mk_test:mnfy_secret')));

        // Step 2: init-transaction with the bearer token, amount in major units, our ref.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/init-transaction')
            && $request['amount'] === 500.0                 // major units (Naira)
            && $request['paymentReference'] === $ref
            && $request['contractCode'] === 'CONTRACT_1'
            && $request['currencyCode'] === 'NGN'
            && $request->hasHeader('Authorization', 'Bearer tok_123'));

        // Monnify's own transactionReference is persisted so refunds (which omit our
        // reference) can correlate back to this funding row.
        $this->assertDatabaseHas('wallet_funding_transactions', ['gateway_ref' => $ref, 'gateway_txn_ref' => 'MNFY|123']);
    }

    public function test_subscription_defaults_to_monnify_checkout_when_live(): void
    {
        $this->goLive('monnify'); // no explicit gateway → manager uses the default
        Http::fake([
            'sandbox.monnify.com/api/v1/auth/login' => Http::response(['responseBody' => ['accessToken' => 'tok_sub']]),
            'sandbox.monnify.com/api/v1/merchant/transactions/init-transaction' => Http::response([
                'responseBody' => ['checkoutUrl' => 'https://sandbox.sdk.monnify.com/checkout/sub'],
            ]),
        ]);

        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        $this->actingAsUser($this->userWithRole('parent'));
        $plan = Plan::where('code', 'premium_individual')->first();

        $ref = $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card'], ['Idempotency-Key' => 'gw-mnfy-sub'])
            ->assertCreated()
            ->assertJsonPath('data.checkout_url', 'https://sandbox.sdk.monnify.com/checkout/sub')
            ->json('data.payment_reference');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/init-transaction')
            && $request['paymentReference'] === $ref
            && $request['amount'] === round($plan->price_minor / 100, 2));
    }

    public function test_no_live_gateway_means_null_checkout_and_no_http(): void
    {
        Http::fake(); // live defaults to false → NullGateway

        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);

        $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'paystack'], ['Idempotency-Key' => 'gw-null'])
            ->assertCreated()
            ->assertJsonPath('data.checkout_url', null);

        Http::assertNothingSent();
    }
}
