<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seed(PlanSeeder::class);

        config([
            'services.payments.live' => true,
            'services.payments.default' => 'monnify',
            'services.monnify.api_key' => 'mk_test',
            'services.monnify.secret' => 'mnfy_secret',
            'services.monnify.contract_code' => 'CONTRACT_1',
            'services.monnify.base_url' => 'https://sandbox.monnify.com',
        ]);
    }

    private function pendingCardSubscription(User $user): Subscription
    {
        $plan = Plan::where('code', 'premium_individual')->firstOrFail();
        $sub = new Subscription(['plan_id' => $plan->id, 'method' => 'card', 'status' => 'pending']);
        $sub->subscriber()->associate($user);
        $sub->save();

        return $sub;
    }

    public function test_retry_activates_when_the_gateway_confirms_payment(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $sub = $this->pendingCardSubscription($parent);

        Http::fake([
            'sandbox.monnify.com/api/v1/auth/login' => Http::response(['responseBody' => ['accessToken' => 'tok_1']]),
            'sandbox.monnify.com/api/v2/merchant/transactions/query*' => Http::response([
                'responseBody' => ['paymentStatus' => 'PAID', 'amountPaid' => 3000],
            ]),
        ]);

        $this->postJson("/api/v1/subscriptions/{$sub->id}/retry", [], ['Idempotency-Key' => 'retry-1'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertSame('active', $sub->fresh()->status);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/transactions/query') && ($r['paymentReference'] ?? null) === "sub_{$sub->id}");
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/init-transaction'));
    }

    public function test_retry_reopens_checkout_when_still_unpaid(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $sub = $this->pendingCardSubscription($parent);

        Http::fake([
            'sandbox.monnify.com/api/v1/auth/login' => Http::response(['responseBody' => ['accessToken' => 'tok_2']]),
            'sandbox.monnify.com/api/v2/merchant/transactions/query*' => Http::response([
                'responseBody' => ['paymentStatus' => 'PENDING'],
            ]),
            'sandbox.monnify.com/api/v1/merchant/transactions/init-transaction' => Http::response([
                'responseBody' => ['checkoutUrl' => 'https://sandbox.sdk.monnify.com/checkout/retry'],
            ]),
        ]);

        $this->postJson("/api/v1/subscriptions/{$sub->id}/retry", [], ['Idempotency-Key' => 'retry-2'])
            ->assertOk()
            ->assertJsonPath('data.checkout_url', 'https://sandbox.sdk.monnify.com/checkout/retry')
            ->assertJsonPath('data.payment_reference', "sub_{$sub->id}");

        $this->assertSame('pending', $sub->fresh()->status);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/init-transaction') && ($r['paymentReference'] ?? null) === "sub_{$sub->id}");
    }

    public function test_retry_reopens_checkout_when_the_gateway_has_never_seen_the_reference(): void
    {
        // A pending row that was never actually sent to the gateway (e.g. the
        // original init call never completed) — querying it 404s rather than
        // returning a PENDING status. That must still fall through to a fresh
        // checkout, not bubble up as a 500.
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $sub = $this->pendingCardSubscription($parent);

        Http::fake([
            'sandbox.monnify.com/api/v1/auth/login' => Http::response(['responseBody' => ['accessToken' => 'tok_404']]),
            'sandbox.monnify.com/api/v2/merchant/transactions/query*' => Http::response([
                'requestSuccessful' => false,
                'responseMessage' => "Could not find transaction with payment reference sub_{$sub->id} for merchant",
            ], 404),
            'sandbox.monnify.com/api/v1/merchant/transactions/init-transaction' => Http::response([
                'responseBody' => ['checkoutUrl' => 'https://sandbox.sdk.monnify.com/checkout/never-seen'],
            ]),
        ]);

        $this->postJson("/api/v1/subscriptions/{$sub->id}/retry", [], ['Idempotency-Key' => 'retry-404'])
            ->assertOk()
            ->assertJsonPath('data.checkout_url', 'https://sandbox.sdk.monnify.com/checkout/never-seen');

        $this->assertSame('pending', $sub->fresh()->status);
    }

    public function test_retry_on_an_already_active_subscription_is_a_noop(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $sub = $this->pendingCardSubscription($parent);
        $sub->update(['status' => 'active']);

        Http::fake();

        $this->postJson("/api/v1/subscriptions/{$sub->id}/retry", [], ['Idempotency-Key' => 'retry-3'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        Http::assertNothingSent();
    }

    public function test_cannot_retry_someone_elses_subscription(): void
    {
        $owner = $this->userWithRole('parent');
        $sub = $this->pendingCardSubscription($owner);

        $this->actingAsUser($this->userWithRole('parent'));

        Http::fake();

        $this->postJson("/api/v1/subscriptions/{$sub->id}/retry", [], ['Idempotency-Key' => 'retry-4'])
            ->assertForbidden();
    }

    public function test_cannot_retry_a_non_card_subscription(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $plan = Plan::where('code', 'premium_individual')->firstOrFail();
        $sub = new Subscription(['plan_id' => $plan->id, 'method' => 'invoice', 'status' => 'active']);
        $sub->subscriber()->associate($parent);
        $sub->save();

        Http::fake();

        $this->postJson("/api/v1/subscriptions/{$sub->id}/retry", [], ['Idempotency-Key' => 'retry-5'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'not_retryable');
    }
}
