<?php

namespace Tests\Feature;

use App\Models\DataBundlePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_totals_filters_and_access(): void
    {
        $this->seedRbac();
        $buyer = $this->userWithRole('parent');
        $this->actingAsUser($buyer);
        $this->getJson('/api/v1/admin/reports/data-sales')->assertForbidden();
        foreach (['success' => 10000, 'processing' => 20000, 'failed' => 30000, 'awaiting_payment' => 40000] as $status => $amount) {
            DataBundlePurchase::create([
                'user_id' => $buyer->id, 'operator' => 'MTN', 'status' => $status,
                'amount_minor' => $amount, 'phone_number' => '08012345678', 'product_name' => 'Weekly data',
                'payment_reference' => 'sale_'.$status, 'paid_at' => $status === 'awaiting_payment' ? null : now(),
            ]);
        }
        $this->actingAsUser($this->userWithRole('super_admin'));
        $this->getJson('/api/v1/admin/reports/data-sales')->assertOk()
            ->assertJsonPath('summary.delivered_sales_minor', 10000)
            ->assertJsonPath('summary.verified_payments_minor', 60000)
            ->assertJsonPath('summary.successful', 1)
            ->assertJsonPath('summary.needs_attention', 1)
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.recipient_last4', '5678');
        $this->getJson('/api/v1/admin/reports/data-sales?status=success&q=sale_success')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('summary.purchases', 4);
        $this->getJson('/api/v1/admin/reports/data-sales?from=2099-01-01')->assertOk()->assertJsonPath('summary.purchases', 0);
        $this->getJson('/api/v1/admin/reports/data-sales?from=2026-10-02&to=2026-09-01')->assertUnprocessable();
    }
}
