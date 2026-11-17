<?php

namespace Tests\Feature;

use App\Models\DataBundlePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataPurchasesPausedTest extends TestCase
{
    use RefreshDatabase;

    public function test_paused_store_rejects_new_checkout_but_keeps_existing_order_status_available(): void
    {
        $this->seedRbac();
        config(['services.data_bundles.enabled' => false]);
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $purchase = DataBundlePurchase::create([
            'user_id' => $parent->id, 'operator' => 'MTN', 'biller_code' => 'MTN',
            'product_code' => 'test', 'product_name' => 'Test bundle',
            'phone_number' => '08012345678', 'amount_minor' => 10000,
            'status' => 'processing', 'payment_reference' => 'data_'.str_repeat('a', 64),
            'vend_reference' => 'vend_test', 'paid_at' => now(), 'vend_started_at' => now(),
        ]);

        $this->getJson('/api/v1/data-bundles/providers')->assertOk()->assertJsonPath('data', []);
        $this->postJson('/api/v1/data-bundles/purchase', [
            'biller_code' => 'MTN', 'product_code' => 'test', 'phone_number' => '08012345678',
            'amount_minor' => 10000, 'consent' => true,
        ], ['Idempotency-Key' => 'paused'])->assertStatus(503);
        $this->assertDatabaseCount('data_bundle_purchases', 1);
        $this->getJson('/api/v1/data-bundles/purchases')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('processing', $purchase->fresh()->status);
    }
}
