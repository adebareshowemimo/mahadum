<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PromoCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceCheckoutReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): Invoice
    {
        $this->seedRbac();
        $org = Organization::create(['name' => 'Checkout School', 'type' => 'school', 'slug' => 'checkout-school', 'status' => 'active', 'contact_email' => 'billing@example.test']);
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $this->actingAsUser($admin);

        return $org->invoices()->create(['type' => 'final', 'amount_minor' => 500000, 'status' => 'unpaid', 'lines' => [['description' => 'School subscription', 'amount_minor' => 500000]]]);
    }

    public static function unavailableProviders(): array
    {
        return [
            'payments disabled' => [false, 'paystack', ['gateway' => 'paystack']],
            'explicit provider missing credentials' => [true, 'paystack', ['gateway' => 'monnify']],
            'default provider missing credentials' => [true, 'monnify', []],
        ];
    }

    #[DataProvider('unavailableProviders')]
    public function test_unavailable_checkout_preserves_invoice_and_allows_promo(bool $live, string $default, array $payload): void
    {
        config([
            'services.payments.live' => $live,
            'services.payments.default' => $default,
            'services.paystack.secret' => 'sk_test',
            'services.monnify.api_key' => null,
            'services.monnify.secret' => null,
            'services.monnify.contract_code' => null,
        ]);
        Http::fake();
        $invoice = $this->invoice();
        $lines = $invoice->breakdownLines();
        $url = "/api/v1/schools/{$invoice->organization_id}/invoices/{$invoice->id}";

        $this->postJson($url.'/pay', $payload)->assertUnprocessable()
            ->assertJsonPath('message', 'This payment provider is currently unavailable. Please try again later.');

        $invoice->refresh();
        $this->assertNull($invoice->gateway_txn_ref);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame(500000, $invoice->amount_minor);
        $this->assertSame($lines, $invoice->breakdownLines());
        Http::assertNothingSent();

        PromoCode::create(['code' => 'SCHOOL20', 'discount_type' => 'percent', 'value' => 20, 'applicable_tier' => 'school']);
        $this->postJson($url.'/promo', ['code' => 'SCHOOL20'])->assertOk()->assertJsonPath('data.amount_minor', 400000);
        $this->assertNull($invoice->fresh()->gateway_txn_ref);
        Http::assertNothingSent();
    }

    public function test_configured_default_checkout_uses_discounted_amount_and_then_locks_promo(): void
    {
        config([
            'services.payments.live' => true,
            'services.payments.default' => 'paystack',
            'services.paystack.secret' => 'sk_test',
            'services.paystack.base_url' => 'https://api.paystack.co',
        ]);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/invoice']])]);
        $invoice = $this->invoice();
        $url = "/api/v1/schools/{$invoice->organization_id}/invoices/{$invoice->id}";
        PromoCode::create(['code' => 'SCHOOL20', 'discount_type' => 'percent', 'value' => 20, 'applicable_tier' => 'school']);
        $this->postJson($url.'/promo', ['code' => 'SCHOOL20'])->assertOk();
        Http::assertNothingSent();

        $this->postJson($url.'/pay')->assertOk()->assertJsonPath('data.checkout_url', 'https://checkout.paystack.com/invoice');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['amount'] === 400000 && $request['reference'] === 'invoice_'.$invoice->id && $request['email'] === 'billing@example.test');
        $this->assertSame('invoice_'.$invoice->id, $invoice->fresh()->gateway_txn_ref);
        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->postJson($url.'/promo', ['code' => 'SCHOOL20'])->assertUnprocessable();
        Http::assertSentCount(1);
    }
}
