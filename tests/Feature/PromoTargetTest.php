<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\PromoCode;
use App\Services\Billing\Gateways\GatewayCheckout;
use App\Services\Billing\Gateways\GatewayTransactionStatus;
use App\Services\Billing\Gateways\PaymentGateway;
use App\Services\Billing\InvoiceLineBuilder;
use App\Services\Billing\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PromoTargetTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): array
    {
        $this->seedRbac();
        $org = Organization::create(['name' => 'Target School', 'slug' => 'target-school', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $this->actingAsUser($admin);
        $breakdown = InvoiceLineBuilder::schoolFees(100000, 20000);
        $invoice = $org->invoices()->create(['type' => 'final', 'status' => 'unpaid', 'amount_minor' => $breakdown['total_minor'], 'lines' => $breakdown['lines']]);

        return [$org, $invoice];
    }

    public function test_registration_discount_leaves_school_fees_payable_and_recomputes_existing_tax(): void
    {
        [$org, $invoice] = $this->invoice();
        PromoCode::create(['code' => 'REGFREE', 'target' => 'school_registration', 'discount_type' => 'percent', 'value' => 100]);
        $this->postJson("/api/v1/schools/{$org->id}/invoices/{$invoice->id}/promo", ['code' => 'REGFREE'])->assertOk()->assertJsonPath('data.amount_minor', 107500);
        $lines = collect($invoice->fresh()->lines);
        $this->assertEquals(100000, $lines->firstWhere('description', InvoiceLineBuilder::STUDENT_SCHOOL_FEES)['amount_minor']);
        $this->assertEquals(7500, $lines->firstWhere('description', 'VAT (7.5%)')['amount_minor']);
        $this->assertDatabaseHas('promo_redemptions', ['organization_id' => $org->id]);
    }

    public function test_school_subscription_discount_uses_student_fees_only(): void
    {
        [$org, $invoice] = $this->invoice();
        PromoCode::create(['code' => 'SCHOOL50', 'target' => 'school_subscription', 'discount_type' => 'percent', 'value' => 50]);
        $this->postJson("/api/v1/schools/{$org->id}/invoices/{$invoice->id}/promo", ['code' => 'SCHOOL50'])->assertOk()->assertJsonPath('data.amount_minor', 75250);
        $this->assertEquals(20000, collect($invoice->fresh()->lines)->firstWhere('description', InvoiceLineBuilder::REGISTRATION_FEES)['amount_minor']);
    }

    public function test_fixed_discount_cannot_exceed_its_target_fee(): void
    {
        [$org, $invoice] = $this->invoice();
        PromoCode::create(['code' => 'REGFIXED', 'target' => 'school_registration', 'discount_type' => 'fixed', 'value' => 500000]);
        $this->postJson("/api/v1/schools/{$org->id}/invoices/{$invoice->id}/promo", ['code' => 'REGFIXED'])->assertOk()->assertJsonPath('data.amount_minor', 107500);
    }

    public function test_wrong_target_and_waived_registration_are_rejected_without_redemption(): void
    {
        [$org, $invoice] = $this->invoice();
        PromoCode::create(['code' => 'PERSONAL', 'target' => 'individual_subscription', 'discount_type' => 'percent', 'value' => 20]);
        $url = "/api/v1/schools/{$org->id}/invoices/{$invoice->id}/promo";
        $this->postJson($url, ['code' => 'PERSONAL'])->assertUnprocessable();
        $breakdown = InvoiceLineBuilder::schoolFees(100000, 0);
        $invoice->update(['amount_minor' => $breakdown['total_minor'], 'lines' => $breakdown['lines']]);
        PromoCode::create(['code' => 'REG', 'target' => 'school_registration', 'discount_type' => 'percent', 'value' => 20]);
        $this->postJson($url, ['code' => 'REG'])->assertUnprocessable();
        $this->assertDatabaseCount('promo_redemptions', 0);
        $this->assertEquals(107500, $invoice->fresh()->amount_minor);
    }

    public function test_individual_preview_and_checkout_reject_school_codes(): void
    {
        $this->seedRbac();
        Notification::fake();
        $this->actingAsUser($this->userWithRole('parent'));
        $plan = Plan::create(['code' => 'premium_individual', 'name' => 'Premium', 'audience' => 'individual', 'price_minor' => 100000, 'currency' => 'NGN', 'interval' => 'month']);
        PromoCode::create(['code' => 'REG', 'target' => 'school_registration', 'discount_type' => 'percent', 'value' => 20]);
        $this->postJson('/api/v1/subscriptions/promo-preview', ['plan_id' => $plan->id, 'code' => 'REG'])->assertUnprocessable()->assertJsonPath('error.code', 'wrong_target');
        $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card', 'promo_code' => 'REG'], ['Idempotency-Key' => 'wrong-promo-target'])->assertUnprocessable();
        $this->assertDatabaseCount('subscriptions', 0);
        PromoCode::create(['code' => 'PERSONAL', 'target' => 'individual_subscription', 'discount_type' => 'percent', 'value' => 20]);
        $this->postJson('/api/v1/subscriptions/promo-preview', ['plan_id' => $plan->id, 'code' => 'PERSONAL'])->assertOk()->assertJsonPath('data.final_minor', 80000);
        $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card', 'promo_code' => 'PERSONAL'], ['Idempotency-Key' => 'individual-promo'])->assertCreated()->assertJsonPath('data.charged_minor', 80000);
    }

    public function test_full_individual_discount_activates_without_gateway_checkout(): void
    {
        $this->seedRbac();
        Notification::fake();
        $this->actingAsUser($this->userWithRole('parent'));
        $plan = Plan::create(['code' => 'premium_individual', 'name' => 'Premium', 'audience' => 'individual', 'price_minor' => 100000, 'currency' => 'NGN', 'interval' => 'month']);
        PromoCode::create(['code' => 'FREE', 'target' => 'individual_subscription', 'discount_type' => 'percent', 'value' => 100]);
        $gateway = \Mockery::mock(PaymentGatewayManager::class);
        $gateway->shouldNotReceive('driver');
        $this->app->instance(PaymentGatewayManager::class, $gateway);
        $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card', 'promo_code' => 'FREE'], ['Idempotency-Key' => 'full-promo'])
            ->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonPath('data.charged_minor', 0);
        $this->assertDatabaseHas('subscriptions', ['status' => 'active', 'initial_charge_minor' => 0]);
    }

    public function test_retry_preserves_original_discounted_charge_even_if_plan_price_changes(): void
    {
        $this->seedRbac();
        Notification::fake();
        $this->actingAsUser($this->userWithRole('parent'));
        $plan = Plan::create(['code' => 'premium_individual', 'name' => 'Premium', 'audience' => 'individual', 'price_minor' => 100000, 'currency' => 'NGN', 'interval' => 'month']);
        PromoCode::create(['code' => 'PERSONAL', 'target' => 'individual_subscription', 'discount_type' => 'percent', 'value' => 20]);
        $subscriptionId = $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id, 'method' => 'card', 'promo_code' => 'PERSONAL'], ['Idempotency-Key' => 'retry-promo'])
            ->assertCreated()->json('data.subscription_id');
        $plan->update(['price_minor' => 150000]);
        $gateway = \Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('verify')->once()->andReturn(new GatewayTransactionStatus('pending'));
        $gateway->shouldReceive('initialize')->once()->with('sub_'.$subscriptionId, 80000, \Mockery::type('string'), \Mockery::type('array'))->andReturn(new GatewayCheckout('sub_'.$subscriptionId, null));
        $manager = \Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('driver')->once()->andReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);
        $this->postJson("/api/v1/subscriptions/{$subscriptionId}/retry", [], ['Idempotency-Key' => 'retry-promo-checkout'])->assertOk();
    }

    public function test_admin_creates_scoped_codes_and_validates_targets_and_percentages(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $url = '/api/v1/admin/promo-codes';
        foreach (['school_registration', 'school_subscription', 'individual_subscription'] as $target) {
            $this->postJson($url, ['code' => strtoupper($target), 'target' => $target, 'discount_type' => 'percent', 'value' => 20])->assertCreated();
            $this->assertDatabaseHas('promo_codes', ['target' => $target]);
        }
        $this->postJson($url, ['code' => 'INVALID', 'target' => 'other', 'discount_type' => 'percent', 'value' => 20])->assertUnprocessable();
        $this->postJson($url, ['code' => 'TOO_MUCH', 'target' => 'school_registration', 'discount_type' => 'percent', 'value' => 101])->assertUnprocessable();
        $this->assertDatabaseHas('audit_logs', ['action' => 'promocode.created']);
    }
}
