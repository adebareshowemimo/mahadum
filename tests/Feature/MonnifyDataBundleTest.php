<?php

namespace Tests\Feature;

use App\Jobs\ProcessDataBundlePurchase;
use App\Models\AuditLog;
use App\Models\DataBundlePurchase;
use App\Services\Billing\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonnifyDataBundleTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = ['biller_code' => 'MTN_DATA', 'product_code' => 'MTN_1GB_7D',
        'phone_number' => '08012345678', 'amount_minor' => 75000, 'consent' => true];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        Http::preventStrayRequests();
        config(['services.monnify.api_key' => 'test', 'services.monnify.secret' => 'secret',
            'services.monnify.contract_code' => 'contract', 'services.monnify.base_url' => 'https://sandbox.monnify.com',
            'services.payments.live' => true]);
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));
        $this->provider();
    }

    public function test_browser_activity_is_authenticated_validated_and_cannot_fake_success(): void
    {
        $payload = ['event' => 'plan_selected', 'session_id' => (string) Str::uuid(), 'product_code' => 'MTN_1GB_7D'];
        $this->postJson('/api/v1/data-bundles/events', $payload)->assertCreated();
        $this->assertDatabaseHas('audit_logs', ['action' => 'billing.data_bundle.ui.plan_selected']);
        $this->postJson('/api/v1/data-bundles/events', array_merge($payload, ['event' => 'success']))->assertUnprocessable();
        $this->postJson('/api/v1/data-bundles/events', array_merge($payload, ['session_id' => 'invalid']))->assertUnprocessable();
    }

    public function test_browser_events_cannot_be_attached_to_another_buyers_purchase(): void
    {
        $result = $this->buy('event-ownership')->assertCreated();
        $this->actingAsUser($this->userWithRole('parent'));
        $this->postJson('/api/v1/data-bundles/events', [
            'event' => 'checkout_opened', 'session_id' => (string) Str::uuid(),
            'purchase_id' => $result->json('data.purchase_id'),
        ])->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'billing.data_bundle.ui.checkout_opened']);
    }

    public function test_server_activity_correlates_success_without_exposing_full_recipient(): void
    {
        $result = $this->postJson('/api/v1/data-bundles/purchase', $this->payload, ['Idempotency-Key' => 'activity'])->assertCreated();
        $this->getJson('/api/v1/data-bundles/purchases/'.$result->json('data.purchase_id'))->assertOk();
        $log = AuditLog::where('action', 'billing.data_bundle.success')->firstOrFail();
        $this->assertSame($result->json('data.purchase_id'), $log->after['purchase_id']);
        $this->assertSame('5678', $log->after['recipient_last4']);
        $this->assertStringNotContainsString('08012345678', json_encode($log->after));
        $this->assertDatabaseHas('audit_logs', ['action' => 'billing.data_bundle.payment_confirmed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'billing.data_bundle.delivery_started']);
    }

    private function provider(int $price = 750, string $paymentStatus = 'PAID', int $paid = 750, string $vendStatus = 'SUCCESS', bool $validationRef = false, array $overrides = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($price, $paymentStatus, $paid, $vendStatus, $validationRef, $overrides) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (isset($overrides[$path])) {
                return $overrides[$path];
            }
            $body = match ($path) {
                '/api/v1/auth/login' => ['accessToken' => 'token'],
                '/api/v1/vas/bills-payment/billers' => ['content' => [['code' => 'MTN_DATA', 'name' => 'MTN Data'], ['code' => 'AIRTEL_DATA', 'name' => 'Airtel Data']], 'last' => true],
                '/api/v1/vas/bills-payment/biller-products' => ['content' => [[
                    'code' => 'MTN_1GB_7D', 'name' => 'MTN 1GB weekly', 'priceType' => 'FIXED', 'price' => $price,
                    'category' => ['code' => 'DATA_BUNDLE'], 'metadata' => ['duration' => 7, 'durationUnit' => 'DAYS'],
                ]], 'last' => true],
                '/api/v1/vas/bills-payment/validate-customer' => ['vendInstruction' => ['requireValidationRef' => $validationRef, 'validationReference' => 'validation-1']],
                '/api/v1/merchant/transactions/init-transaction' => ['checkoutUrl' => 'https://sandbox.monnify.com/checkout/test'],
                '/api/v2/merchant/transactions/query' => ['paymentStatus' => $paymentStatus, 'amountPaid' => $paid, 'currencyCode' => 'NGN', 'paymentReference' => $request['paymentReference']],
                '/api/v1/vas/bills-payment/vend', '/api/v1/vas/bills-payment/requery' => [
                    'vendStatus' => $vendStatus, 'vendReference' => $request['reference'], 'productCode' => 'MTN_1GB_7D',
                    'customerId' => '08012345678', 'vendAmount' => 750,
                ],
                default => throw new \RuntimeException('Unexpected endpoint '.$path),
            };

            return Http::response(['requestSuccessful' => true, 'responseBody' => $body]);
        });
    }

    private function buy(string $key = 'purchase-1', array $overrides = [])
    {
        return $this->postJson('/api/v1/data-bundles/purchase', array_replace($this->payload, $overrides), ['Idempotency-Key' => $key]);
    }

    public function test_catalogue_uses_provider_names_codes_prices_and_validity(): void
    {
        $this->getJson('/api/v1/data-bundles/billers')->assertOk()->assertJsonPath('data.0.code', 'MTN_DATA');
        $this->getJson('/api/v1/data-bundles?biller_code=MTN_DATA')->assertOk()
            ->assertJsonPath('data.0.product_code', 'MTN_1GB_7D')->assertJsonPath('data.0.amount_minor', 75000)
            ->assertJsonPath('data.0.duration', 7)->assertJsonPath('data.0.name', 'MTN 1GB weekly');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/billers') && $r['category_code'] === 'DATA_BUNDLE');
    }

    public function test_catalogue_loads_every_page_without_truncating_short_pages(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*/api/v1/auth/login' => Http::response(['responseBody' => ['accessToken' => 'token']]),
            '*/billers*' => Http::response(['requestSuccessful' => true, 'responseBody' => ['content' => [['code' => 'MTN_DATA', 'name' => 'MTN']], 'last' => true]]),
            '*/biller-products*' => Http::sequence()
                ->push(['requestSuccessful' => true, 'responseBody' => ['content' => [['code' => 'p1', 'name' => 'Weekly', 'price' => '750.50', 'priceType' => 'FIXED', 'category' => ['code' => 'DATA_BUNDLE']]], 'totalPages' => 2, 'last' => false]])
                ->push(['requestSuccessful' => true, 'responseBody' => ['content' => [['code' => 'p2', 'name' => 'Monthly', 'price' => 1000, 'priceType' => 'FIXED', 'category' => ['code' => 'DATA_BUNDLE']]], 'totalPages' => 2, 'last' => true]]),
        ]);
        $this->getJson('/api/v1/data-bundles?biller_code=MTN_DATA')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.amount_minor', 75050)->assertJsonPath('data.1.product_code', 'p2');
    }

    public function test_missing_credentials_and_provider_errors_do_not_return_fake_plans(): void
    {
        config(['services.monnify.api_key' => null]);
        $this->getJson('/api/v1/data-bundles/billers')->assertStatus(503);
        Http::assertNothingSent();
        config(['services.monnify.api_key' => 'test']);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*/api/v1/auth/login' => Http::response(['responseBody' => ['accessToken' => 'token']]), '*/billers*' => Http::response([], 502)]);
        $this->getJson('/api/v1/data-bundles/billers')->assertStatus(502)->assertJsonMissingPath('data');
    }

    public function test_checkout_requires_enabled_payments_current_price_and_valid_product(): void
    {
        config(['services.payments.live' => false]);
        $this->buy()->assertStatus(503);
        Http::assertNothingSent();
        config(['services.payments.live' => true]);
        $this->buy('price', ['amount_minor' => 50000])->assertStatus(422);
        $this->buy('unknown', ['product_code' => 'invented'])->assertStatus(422);
        $this->assertDatabaseCount('data_bundle_purchases', 0);
    }

    public function test_purchase_validates_recipient_and_requires_consent(): void
    {
        $this->buy('phone', ['phone_number' => '123'])->assertUnprocessable()->assertJsonValidationErrors('phone_number');
        $this->buy('consent', ['consent' => false])->assertUnprocessable()->assertJsonValidationErrors('consent');
        Http::assertNothingSent();
    }

    public function test_validation_failure_never_opens_checkout(): void
    {
        $this->provider(overrides: ['/api/v1/vas/bills-payment/validate-customer' => Http::response(['requestSuccessful' => false], 422)]);
        $this->buy()->assertStatus(502);
        $this->assertDatabaseCount('data_bundle_purchases', 0);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'init-transaction'));
    }

    public function test_checkout_is_idempotent_and_does_not_vend_before_payment(): void
    {
        $first = $this->buy()->assertCreated()->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.checkout_url', 'https://sandbox.monnify.com/checkout/test');
        $this->buy()->assertCreated()->assertJsonPath('data.purchase_id', $first->json('data.purchase_id'));
        $this->assertDatabaseCount('data_bundle_purchases', 1);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/vend'));
        Queue::assertPushed(ProcessDataBundlePurchase::class);
        $this->provider(paymentStatus: 'PENDING');
        $this->getJson('/api/v1/data-bundles/purchases/'.$first->json('data.purchase_id'))->assertOk()->assertJsonPath('data.status', 'awaiting_payment');
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/vend'));
    }

    public function test_payment_amount_mismatch_never_vends(): void
    {
        $id = $this->buy()->json('data.purchase_id');
        $this->provider(paid: 100);
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'needs_review');
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/vend'));
    }

    public function test_verified_payment_vends_once_and_omits_unneeded_validation_reference(): void
    {
        $id = $this->buy()->json('data.purchase_id');
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'success');
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'success');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/vend') && $r['amount'] == 750 && ! isset($r['validationReference']));
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/vend')));
    }

    public function test_required_validation_reference_is_used_and_in_progress_is_only_requeried(): void
    {
        $this->provider(vendStatus: 'IN_PROGRESS', validationRef: true);
        $id = $this->buy()->json('data.purchase_id');
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'processing');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/vend') && $r['validationReference'] === 'validation-1');
        $this->provider();
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'success');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/requery') && str_starts_with($r['reference'], 'vend_data_'));
    }

    public function test_timeout_after_vend_is_requeried_without_another_charge(): void
    {
        $id = $this->buy()->json('data.purchase_id');
        $this->provider(overrides: ['/api/v1/vas/bills-payment/vend' => Http::failedConnection()]);
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertStatus(503);
        $this->assertNotNull(DataBundlePurchase::find($id)->vend_started_at);
        $this->provider();
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'success');
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/vend'));
    }

    public function test_other_users_cannot_inspect_or_fulfill_a_purchase(): void
    {
        $id = $this->buy()->json('data.purchase_id');
        $this->actingAsUser($this->userWithRole('parent'));
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertForbidden();
    }

    public function test_real_catalogue_shape_excludes_airtime_and_follows_next_page(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*/api/v1/auth/login' => Http::response(['responseBody' => ['accessToken' => 'token']]),
            '*/billers*' => Http::response(['requestSuccessful' => true, 'responseBody' => [
                'content' => [['code' => 'MTN_DATA', 'name' => 'MTN']], 'totalElements' => 1, 'nextPage' => null,
            ]]),
            '*/biller-products*' => Http::sequence()
                ->push(['requestSuccessful' => true, 'responseBody' => [
                    'content' => [['code' => 'airtime', 'name' => 'Mobile Top up', 'category' => ['code' => 'AIRTIME'], 'priceType' => 'OPEN']],
                    'totalElements' => 2, 'nextPage' => 1,
                ]])
                ->push(['requestSuccessful' => true, 'responseBody' => [
                    'content' => [['code' => '19523', 'name' => 'DataPlan 750MB', 'category' => ['code' => 'DATA_BUNDLE'],
                        'priceType' => 'FIXED', 'price' => 500, 'metadata' => ['duration' => 1, 'durationUnit' => 'WEEKLY']]],
                    'totalElements' => 2, 'nextPage' => null,
                ]]),
        ]);
        $this->getJson('/api/v1/data-bundles?biller_code=MTN_DATA')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_code', '19523')->assertJsonPath('data.0.amount_minor', 50000);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/biller-products') && (int) $r['page'] === 1);
    }

    public function test_database_idempotency_survives_cache_expiration(): void
    {
        $id = $this->buy()->json('data.purchase_id');
        Cache::flush();
        $this->buy()->assertCreated()->assertJsonPath('data.purchase_id', $id);
        $this->assertDatabaseCount('data_bundle_purchases', 1);
        $this->assertCount(1, Http::recorded(fn ($r) => str_contains($r->url(), '/init-transaction')));
        Cache::flush();
        $this->buy(overrides: ['phone_number' => '08012345679'])->assertConflict();
    }

    public function test_payment_notification_wakes_reconciliation_and_refund_stops_fulfillment(): void
    {
        $id = $this->buy()->json('data.purchase_id');
        $purchase = DataBundlePurchase::find($id);
        $purchase->update(['gateway_txn_ref' => 'MNFY-TXN-1']);
        $payments = app(PaymentService::class);
        $this->assertSame('data_payment_received', $payments->process('monnify', 'paid-1', $purchase->payment_reference, 'success', 75000, []));
        $this->assertSame('duplicate', $payments->process('monnify', 'paid-1', $purchase->payment_reference, 'success', 75000, []));
        $this->assertNull($purchase->fresh()->paid_at);
        $this->assertSame('reversed', $payments->process('monnify', 'refund-1', 'MNFY-TXN-1', 'refund', 75000, []));
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'needs_review');
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/vend'));
    }
}
