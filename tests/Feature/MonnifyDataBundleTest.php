<?php

namespace Tests\Feature;

use App\Jobs\ProcessDataBundlePurchase;
use App\Models\AuditLog;
use App\Models\DataBundlePurchase;
use App\Services\Billing\DataPurchaseActivity;
use App\Services\Billing\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Queue\Connectors\DatabaseConnector;
use Illuminate\Queue\QueueManager;
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
                    'vendStatus' => $vendStatus, 'vendReference' => $request['vendReference'], 'productCode' => 'MTN_1GB_7D',
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

    public function test_data_checkout_returns_to_the_status_page(): void
    {
        config(['app.url' => 'https://mahadum360.com/']);
        $this->buy('data-return')->assertCreated();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/init-transaction')
            && $request['redirectUrl'] === 'https://mahadum360.com/billing/data/status'
            && $request['metadata']['purpose'] === 'data_bundle'
            && $request['paymentDescription'] === 'Mahadum360 mobile data purchase');
    }

    public function test_deployed_worker_delivers_paid_data_without_a_webhook_or_browser_status_check(): void
    {
        config(['queue.default' => 'database']);
        $manager = new QueueManager($this->app);
        $manager->addConnector('database', fn () => new DatabaseConnector($this->app['db']));
        Queue::swap($manager);
        $id = $this->buy('worker-delivery')->assertCreated()->json('data.purchase_id');
        $this->assertDatabaseHas('jobs', ['queue' => 'data-bundles']);
        $this->assertDatabaseHas('data_bundle_purchases', ['id' => $id, 'status' => 'awaiting_payment']);

        // Exercise the queues in the shipped unit, rather than calling the job directly.
        $unit = file_get_contents(base_path('deploy/systemd/mahadum-queue.service'));
        preg_match('/^ExecStart=.*?--queue=([^\s]+)/m', $unit, $matches);
        $queues = $matches[1] ?? config('queue.connections.database.queue');
        $this->travel(31)->seconds();
        $this->artisan('queue:work', ['--once' => true, '--queue' => $queues, '--sleep' => 0])->assertExitCode(0);

        $this->assertDatabaseHas('data_bundle_purchases', ['id' => $id, 'status' => 'success']);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('webhook_events', 0);
        $this->assertNotNull(DataBundlePurchase::findOrFail($id)->paid_at);
        $this->assertSame(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/vend'))->count());
    }

    public function test_existing_data_checkout_returns_redirect_to_the_status_page_with_only_the_reference(): void
    {
        $query = ['paymentReference' => 'data_'.str_repeat('a', 64), 'paymentStatus' => 'CANCELLED'];
        foreach (['/', '/billing/data'] as $path) {
            $this->get($path.'?'.http_build_query($query))
                ->assertRedirect('/billing/data/status?'.http_build_query(['paymentReference' => $query['paymentReference']]));
        }
    }

    public function test_return_reference_verifies_the_exact_purchase_and_delivers_only_once(): void
    {
        $first = $this->buy('earlier-order')->assertCreated()->json('data');
        $current = $this->buy('returned-order')->assertCreated()->json('data');
        $url = '/api/v1/data-bundles/purchases/verify?'.http_build_query(['payment_reference' => $current['payment_reference']]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.purchase_id', $current['purchase_id'])
            ->assertJsonPath('data.status', 'success')->assertJsonPath('data.payment_confirmed', true)->assertJsonPath('data.delivery_started', true);
        $this->getJson($url)->assertOk()->assertJsonPath('data.status', 'success');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/merchant/transactions/query') && $r['paymentReference'] === $current['payment_reference']);
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/vend')));
        $this->assertDatabaseHas('data_bundle_purchases', ['id' => $first['purchase_id'], 'status' => 'awaiting_payment', 'paid_at' => null]);
    }

    public function test_return_reference_requires_owner_permission_and_valid_reference_without_provider_calls(): void
    {
        $reference = $this->buy()->assertCreated()->json('data.payment_reference');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $url = '/api/v1/data-bundles/purchases/verify?'.http_build_query(['payment_reference' => $reference]);
        $this->getJson('/api/v1/data-bundles/purchases/verify')->assertUnprocessable();
        $this->getJson('/api/v1/data-bundles/purchases/verify?payment_reference=bad')->assertUnprocessable();
        $this->actingAsUser($this->userWithRole('parent'));
        $this->getJson($url)->assertNotFound();
        $this->getJson('/api/v1/data-bundles/purchases/verify?payment_reference=data_'.str_repeat('f', 64))->assertNotFound();
        $this->actingAsUser($this->userWithRole('student'));
        $this->getJson($url)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_return_status_cannot_confirm_pending_payment_and_mismatches_never_vend(): void
    {
        $reference = $this->buy()->assertCreated()->json('data.payment_reference');
        $url = '/api/v1/data-bundles/purchases/verify?'.http_build_query(['payment_reference' => $reference, 'paymentStatus' => 'PAID']);
        $this->provider(paymentStatus: 'PENDING');
        $this->getJson($url)->assertOk()->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.payment_confirmed', false)->assertJsonPath('data.delivery_started', false);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/vend'));
        $this->provider(paid: 749);
        $this->getJson($url)->assertOk()->assertJsonPath('data.status', 'needs_review')->assertJsonPath('data.payment_confirmed', false);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/vend'));
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

    public function test_access_rejection_explains_activation_without_exposing_provider_details(): void
    {
        $this->provider(overrides: ['/api/v1/vas/bills-payment/billers' => Http::response([
            'requestSuccessful' => false, 'responseCode' => '99', 'responseMessage' => 'private merchant details',
        ], 406)]);
        $response = $this->getJson('/api/v1/data-bundles/billers')->assertStatus(502)
            ->assertJsonPath('message', 'Mobile data plans are unavailable because Monnify Bills Payment access is not enabled or was rejected. Please contact support.');
        $this->assertStringNotContainsString('private merchant details', $response->getContent());
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/vend'));
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

    public function test_verified_currency_alias_recovers_a_previously_held_payment_without_duplicate_vending(): void
    {
        $id = $this->buy('currency-alias')->json('data.purchase_id');
        $purchase = DataBundlePurchase::findOrFail($id);
        $payment = ['paymentStatus' => 'PAID', 'amountPaid' => 750,
            'paymentReference' => $purchase->payment_reference];
        $this->provider(overrides: ['/api/v2/merchant/transactions/query' => Http::response([
            'responseBody' => $payment + ['currency' => 'NGN'],
        ])]);
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()
            ->assertJsonPath('data.status', 'success')->assertJsonPath('data.payment_confirmed', true);
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/vend')));

        // Simulate a row held by the previous code, which required currencyCode.
        $held = $this->buy('held-currency-alias')->json('data.purchase_id');
        $heldPurchase = DataBundlePurchase::findOrFail($held);
        $this->provider(overrides: ['/api/v2/merchant/transactions/query' => Http::response([
            'responseBody' => array_replace($payment, ['paymentReference' => $heldPurchase->payment_reference]),
        ])]);
        $this->getJson('/api/v1/data-bundles/purchases/'.$held)->assertOk()
            ->assertJsonPath('data.status', 'needs_review')->assertJsonPath('data.payment_confirmed', false);
        // The provider now confirms the same purchase with an accepted currency field.
        $this->provider(overrides: ['/api/v2/merchant/transactions/query' => Http::response([
            'responseBody' => array_replace($payment, [
                'paymentReference' => $heldPurchase->payment_reference, 'currency' => 'NGN',
            ]),
        ])]);
        $this->getJson('/api/v1/data-bundles/purchases/'.$held)->assertOk()
            ->assertJsonPath('data.status', 'success')->assertJsonPath('data.payment_confirmed', true);
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/vend')));
    }

    public function test_refunded_review_order_cannot_be_reopened_by_a_paid_verification(): void
    {
        $id = $this->buy('refunded-review')->json('data.purchase_id');
        $purchase = DataBundlePurchase::findOrFail($id);
        $purchase->update(['status' => 'needs_review']);
        $activity = app(DataPurchaseActivity::class);
        $activity->record('payment_mismatch', $purchase);
        $activity->record('refund_received', $purchase);

        Http::swap(new Factory);
        Http::preventStrayRequests();
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()
            ->assertJsonPath('data.status', 'needs_review');
        Http::assertNothingSent();
    }

    public function test_verified_payment_vends_once_and_omits_unneeded_validation_reference(): void
    {
        $id = $this->buy()->json('data.purchase_id');
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'success');
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'success');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/vend') && $r['vendAmount'] == 750
            && str_starts_with($r['vendReference'], 'vend_data_') && ! isset($r['amount']) && ! isset($r['reference'])
            && ! isset($r['emailAddress']) && ! isset($r['phoneNumber']) && ! isset($r['validationReference']));
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/vend')));
    }

    public function test_documented_minimal_success_response_confirms_delivery_without_a_second_vend(): void
    {
        $id = $this->buy('minimal-vend-response')->json('data.purchase_id');
        $reference = DataBundlePurchase::findOrFail($id)->vend_reference;
        $this->provider(overrides: ['/api/v1/vas/bills-payment/vend' => Http::response([
            'requestSuccessful' => true, 'responseBody' => [
                'transactionReference' => 'MNFY-VEND-1', 'vendReference' => $reference, 'vendStatus' => 'SUCCESS',
            ],
        ])]);

        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()
            ->assertJsonPath('data.status', 'success')->assertJsonPath('data.payment_confirmed', true);
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'success');
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/vend')));
    }

    public function test_signed_payment_webhook_wakes_verification_without_trusting_the_webhook_for_delivery(): void
    {
        $purchase = $this->buy('signed-data-payment')->assertCreated()->json('data');
        $body = json_encode(['eventType' => 'SUCCESSFUL_TRANSACTION', 'eventData' => [
            'paymentReference' => $purchase['payment_reference'], 'transactionReference' => 'MNFY-DATA-1',
            'paymentStatus' => 'PAID', 'amountPaid' => 750, 'currencyCode' => 'NGN',
        ]]);

        $this->call('POST', '/api/v1/webhooks/monnify', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_MONNIFY_SIGNATURE' => hash_hmac('sha512', $body, 'secret'),
        ], $body)->assertOk()->assertJsonPath('status', 'data_payment_received');
        $this->assertNull(DataBundlePurchase::findOrFail($purchase['purchase_id'])->paid_at);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/vend'));
        Queue::assertPushed(ProcessDataBundlePurchase::class);

        $this->getJson('/api/v1/data-bundles/purchases/verify?payment_reference='.$purchase['payment_reference'])
            ->assertOk()->assertJsonPath('data.status', 'success');
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/vend')));
    }

    public function test_required_validation_reference_is_used_and_in_progress_is_only_requeried(): void
    {
        $this->provider(vendStatus: 'IN_PROGRESS', validationRef: true);
        $id = $this->buy()->json('data.purchase_id');
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'processing');
        $this->assertSame('IN_PROGRESS', AuditLog::where('action', 'billing.data_bundle.delivery_result')->latest('id')->firstOrFail()->after['provider_vend_status']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/vend') && $r['validationReference'] === 'validation-1');
        $this->provider();
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'success');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/requery') && str_starts_with($r['vendReference'], 'vend_data_') && ! isset($r['reference']));
    }

    public function test_top_level_validation_instructions_can_complete_a_paid_purchase(): void
    {
        $id = $this->buy('top-level-validation')->json('data.purchase_id');
        $this->provider(overrides: ['/api/v1/vas/bills-payment/validate-customer' => Http::response([
            'requestSuccessful' => true, 'responseBody' => [
                'requireValidationRef' => true, 'validationReference' => 'validation-top-level',
            ],
        ])]);

        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()
            ->assertJsonPath('data.status', 'success');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/vend')
            && $r['validationReference'] === 'validation-top-level');
    }

    public function test_timeout_after_vend_is_requeried_without_another_charge(): void
    {
        $id = $this->buy()->json('data.purchase_id');
        $this->provider(overrides: ['/api/v1/vas/bills-payment/vend' => Http::failedConnection()]);
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()
            ->assertJsonPath('data.status', 'processing')->assertJsonPath('data.payment_confirmed', true)
            ->assertJsonPath('data.check_issue', 'provider_unavailable');
        $this->assertNotNull(DataBundlePurchase::find($id)->vend_started_at);
        $this->provider();
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'success');
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/vend'));
    }

    public function test_provider_rejection_keeps_paid_order_visible_and_records_diagnostics_without_duplicate_vend(): void
    {
        $id = $this->buy('provider-rejection')->json('data.purchase_id');
        $this->provider(overrides: ['/api/v1/vas/bills-payment/vend' => Http::response([
            'requestSuccessful' => false, 'responseCode' => 'BALANCE_LOW',
            'responseMessage' => 'Insufficient merchant balance for 08012345678',
        ], 400)]);
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()
            ->assertJsonPath('data.status', 'processing')->assertJsonPath('data.payment_confirmed', true)
            ->assertJsonPath('data.check_issue', 'provider_unavailable');
        $log = AuditLog::where('action', 'billing.data_bundle.processing_error')->latest('id')->firstOrFail();
        $this->assertSame($id, $log->subject_id);
        $this->assertSame('vend', $log->after['endpoint']);
        $this->assertSame(400, $log->after['provider_http_status']);
        $this->assertSame('BALANCE_LOW', $log->after['provider_code']);
        $this->assertSame('Insufficient merchant balance for [redacted]', $log->after['provider_message']);
        $this->assertSame(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/vend'))->count());

        $this->provider(vendStatus: 'IN_PROGRESS');
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'processing');
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/vend'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/requery'));
    }

    public function test_malformed_validation_instructions_never_start_delivery(): void
    {
        $id = $this->buy()->json('data.purchase_id');
        $this->provider(overrides: ['/api/v1/vas/bills-payment/validate-customer' => Http::response([
            'requestSuccessful' => true, 'responseBody' => ['vendInstruction' => ['requireValidationRef' => 'false']],
        ])]);
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertStatus(502);
        $this->assertNull(DataBundlePurchase::findOrFail($id)->vend_started_at);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/vend'));
    }

    public function test_mismatched_delivery_confirmation_is_reviewed_without_another_vend(): void
    {
        $id = $this->buy()->json('data.purchase_id');
        $purchase = DataBundlePurchase::findOrFail($id);
        $this->provider(overrides: ['/api/v1/vas/bills-payment/vend' => Http::response([
            'requestSuccessful' => true, 'responseBody' => ['vendStatus' => 'SUCCESS',
                'vendReference' => $purchase->vend_reference, 'productCode' => $purchase->product_code,
                'customerId' => '08012345679', 'vendAmount' => 750],
        ])]);
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'needs_review');
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'needs_review');
        $this->assertCount(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/vend')));
    }

    public function test_other_users_cannot_inspect_or_fulfill_a_purchase(): void
    {
        $id = $this->buy()->json('data.purchase_id');
        $this->actingAsUser($this->userWithRole('parent'));
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertForbidden();
    }

    public function test_history_keeps_unresolved_purchases_and_only_lists_the_buyers_orders_without_vending(): void
    {
        $owner = auth()->user();
        $oldId = $this->buy('old-review-order')->assertCreated()->json('data.purchase_id');
        DataBundlePurchase::findOrFail($oldId)->update(['status' => 'needs_review', 'paid_at' => now()]);
        $newId = $this->buy('separate-new-order')->assertCreated()->json('data.purchase_id');
        DataBundlePurchase::create(['user_id' => $owner->id, 'operator' => 'MTN', 'bundle_mb' => 1000,
            'amount_minor' => 75000, 'status' => 'pending']); // Legacy scaffold with no checkout.
        $this->actingAsUser($this->userWithRole('parent'));
        $otherId = $this->buy('another-buyers-order')->assertCreated()->json('data.purchase_id');
        $this->actingAsUser($owner);

        Http::swap(new Factory);
        Http::preventStrayRequests();
        $this->getJson('/api/v1/data-bundles/purchases')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.purchase_id', $newId)
            ->assertJsonPath('data.1.purchase_id', $oldId)->assertJsonPath('data.1.status', 'needs_review')
            ->assertJsonMissing(['purchase_id' => $otherId]);
        Http::assertNothingSent();
        $this->assertDatabaseHas('data_bundle_purchases', ['id' => $oldId, 'status' => 'needs_review']);
        $this->actingAsUser($this->userWithRole('student'));
        $this->getJson('/api/v1/data-bundles/purchases')->assertForbidden();
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
