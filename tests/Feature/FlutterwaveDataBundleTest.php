<?php

namespace Tests\Feature;

use App\Models\DataBundlePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FlutterwaveDataBundleTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = [
        'provider' => 'flutterwave', 'biller_code' => 'BIL108', 'product_code' => 'MD142',
        'phone_number' => '08012345678', 'amount_minor' => 20000, 'consent' => true,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        Http::preventStrayRequests();
        config([
            'services.payments.live' => true,
            'services.flutterwave.public_key' => 'test-public',
            'services.flutterwave.secret' => 'test-secret',
            'services.flutterwave.encryption_key' => 'test-encryption',
            'services.flutterwave.secret_hash' => 'test-hash',
            'services.flutterwave.enabled' => true,
            'services.flutterwave.bills_enabled' => true,
            'services.flutterwave.base_url' => 'https://api.flutterwave.com/v3',
        ]);
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));
        $this->provider();
    }

    private function provider(string $currency = 'NGN', bool $billError = false): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($currency, $billError) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($billError && $path === '/v3/billers/BIL108/items/MD142/payment') {
                return Http::response(['status' => 'error', 'message' => 'temporary error'], 502);
            }
            $reference = DataBundlePurchase::whereNotNull('payment_reference')->latest('id')->value('payment_reference');
            $vendReference = $reference ? 'vend_'.$reference : null;
            $data = match ($path) {
                '/v3/top-bill-categories' => [['code' => 'MOBILEDATA', 'name' => 'Mobile Data']],
                '/v3/bills/MOBILEDATA/billers' => [['biller_code' => 'BIL108', 'name' => 'MTN']],
                '/v3/billers/BIL108/items' => [['item_code' => 'MD142', 'name' => 'MTN 200 MB', 'amount' => 200]],
                '/v3/payments' => ['link' => 'https://checkout.flutterwave.com/test'],
                '/v3/transactions/verify_by_reference' => [
                    'status' => 'successful', 'tx_ref' => $request['tx_ref'], 'currency' => $currency, 'amount' => 200,
                ],
                '/v3/billers/BIL108/items/MD142/payment' => ['reference' => $vendReference, 'tx_ref' => 'CF-FLYAPI-1', 'status' => 'pending'],
                '/v3/bills/CF-FLYAPI-1' => [
                    'status' => 'success', 'customer_reference' => $vendReference,
                    'customer_id' => '08012345678', 'amount' => 200,
                ],
                default => throw new \RuntimeException('Unexpected Flutterwave path '.$path),
            };

            return Http::response(['status' => 'success', 'data' => $data]);
        });
    }

    public function test_flutterwave_data_purchase_verifies_payment_and_vends_once(): void
    {
        $this->getJson('/api/v1/data-bundles/providers')->assertOk()->assertJsonPath('data.0', 'flutterwave');
        $this->getJson('/api/v1/data-bundles/billers?provider=flutterwave')->assertOk()->assertJsonPath('data.0.code', 'BIL108');
        $this->getJson('/api/v1/data-bundles?provider=flutterwave&biller_code=BIL108')->assertOk()->assertJsonPath('data.0.product_code', 'MD142');

        $order = $this->postJson('/api/v1/data-bundles/purchase', $this->payload, ['Idempotency-Key' => 'flw-purchase'])
            ->assertCreated()->assertJsonPath('data.payment_provider', 'flutterwave')->json('data');
        $id = $order['purchase_id'];
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'processing');
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'success');
        $this->postJson('/api/v1/data-bundles/purchase', $this->payload, ['Idempotency-Key' => 'flw-purchase'])->assertCreated();
        $this->assertDatabaseCount('data_bundle_purchases', 1);
        $this->assertSame(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/payment'))->count());
        $this->assertDatabaseHas('data_bundle_purchases', ['id' => $id, 'payment_provider' => 'flutterwave',
            'fulfillment_provider' => 'flutterwave', 'bill_tx_ref' => 'CF-FLYAPI-1', 'status' => 'success']);
    }

    public function test_combined_catalogue_keeps_flutterwave_offer_identifiers(): void
    {
        $this->getJson('/api/v1/data-bundles/networks')->assertOk()->assertJsonPath('data.0.code', 'MTN');
        $this->getJson('/api/v1/data-bundles/offers?network=MTN')->assertOk()
            ->assertJsonPath('data.0.provider', 'flutterwave')
            ->assertJsonPath('data.0.biller_code', 'BIL108')
            ->assertJsonPath('data.0.product_code', 'MD142')
            ->assertJsonPath('data.0.amount_minor', 20000);
    }

    public function test_unreachable_catalogue_is_not_offered_to_customers(): void
    {
        Cache::flush();
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'IP not allowed'], 400)]);

        $this->getJson('/api/v1/data-bundles/providers')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/v1/data-bundles/networks')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/v1/data-bundles/offers?network=MTN')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_wrong_payment_currency_never_vends(): void
    {
        $this->provider('USD');
        $id = $this->postJson('/api/v1/data-bundles/purchase', $this->payload, ['Idempotency-Key' => 'flw-currency'])->json('data.purchase_id');
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'needs_review');
        $this->assertSame(0, Http::recorded(fn ($r) => str_ends_with($r->url(), '/payment'))->count());
    }

    public function test_uncertain_bill_submission_requeries_without_a_second_purchase(): void
    {
        $this->provider('NGN', billError: true);
        $id = $this->postJson('/api/v1/data-bundles/purchase', $this->payload, ['Idempotency-Key' => 'flw-uncertain'])->json('data.purchase_id');
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertStatus(502);
        $this->assertDatabaseHas('data_bundle_purchases', ['id' => $id, 'status' => 'processing']);
        $purchase = DataBundlePurchase::findOrFail($id);
        $this->assertNotNull($purchase->vend_started_at);
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'processing');
        $this->postJson('/api/v1/webhooks/flutterwave', [
            'event' => 'singlebillpayment.status',
            'data' => ['customer_reference' => $purchase->vend_reference, 'tx_ref' => 'CF-FLYAPI-1', 'status' => 'success'],
        ], ['verif-hash' => 'test-hash'])->assertOk();
        $this->getJson('/api/v1/data-bundles/purchases/'.$id)->assertOk()->assertJsonPath('data.status', 'success');
        $this->assertSame(1, Http::recorded(fn ($r) => str_ends_with($r->url(), '/payment'))->count());
    }

    public function test_flutterwave_data_stays_disabled_until_explicitly_enabled(): void
    {
        config(['services.flutterwave.bills_enabled' => false]);
        $this->postJson('/api/v1/data-bundles/purchase', $this->payload, ['Idempotency-Key' => 'flw-disabled'])->assertStatus(503);
        $this->assertDatabaseCount('data_bundle_purchases', 0);
    }
}
