<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PromoCode;
use App\Services\Billing\InvoiceLineBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoicePromoPresentationTest extends TestCase
{
    use RefreshDatabase;

    public static function promoCases(): array
    {
        return [
            'registration target' => ['REG50', 'school_registration', 50, 100000, 1182500, 'School registration'],
            'registration in code' => ['REGISTRATION20', 'all', 20, 258000, 1032000, 'All fees'],
            'student and school in code' => ['STUDENT_SCHOOL50', 'school_subscription', 50, 500000, 752500, 'School subscription'],
        ];
    }

    private function invoice(): array
    {
        $this->seedRbac();
        $org = Organization::create(['name' => 'Promo School', 'type' => 'school', 'slug' => 'promo-school', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $this->actingAsUser($admin);
        $breakdown = InvoiceLineBuilder::schoolFees(1000000, 200000);
        $invoice = $org->invoices()->create([
            'type' => 'proforma', 'status' => 'unpaid', 'issued_at' => now(),
            'amount_minor' => $breakdown['total_minor'], 'lines' => $breakdown['lines'],
        ]);

        return [$org, $invoice];
    }

    #[DataProvider('promoCases')]
    public function test_invoice_api_preserves_promo_label_and_signed_discount(string $code, string $target, int $percent, int $discount, int $total, string $targetLabel): void
    {
        [$org, $invoice] = $this->invoice();
        PromoCode::create(['code' => $code, 'target' => $target, 'discount_type' => 'percent', 'value' => $percent]);
        $this->postJson("/api/v1/schools/{$org->id}/invoices/{$invoice->id}/promo", ['code' => $code])->assertOk()
            ->assertJsonPath('data.amount_minor', $total);

        $this->getJson("/api/v1/schools/{$org->id}/invoices")->assertOk()
            ->assertJsonPath('data.0.id', $invoice->id)->assertJsonPath('data.0.amount_minor', $total)
            ->assertJsonFragment(['description' => 'Student School Fees', 'amount_minor' => 1000000])
            ->assertJsonFragment(['description' => 'Registration Fees', 'amount_minor' => 200000])
            ->assertJsonFragment(['description' => "Promo code: {$code} ({$targetLabel})", 'amount_minor' => -$discount]);
        $this->assertDatabaseCount('promo_redemptions', 1);
    }

    #[DataProvider('promoCases')]
    public function test_another_code_cannot_stack_after_a_promo_with_fee_words(string $code, string $target, int $percent, int $discount, int $total, string $targetLabel): void
    {
        [$org, $invoice] = $this->invoice();
        $first = PromoCode::create(['code' => $code, 'target' => $target, 'discount_type' => 'percent', 'value' => $percent]);
        $second = PromoCode::create(['code' => 'SECOND10', 'target' => 'all', 'discount_type' => 'percent', 'value' => 10]);
        $url = "/api/v1/schools/{$org->id}/invoices/{$invoice->id}/promo";
        $this->postJson($url, ['code' => $code])->assertOk()->assertJsonPath('data.amount_minor', $total);
        $beforeLines = $invoice->fresh()->lines;

        $this->postJson($url, ['code' => 'SECOND10'])->assertUnprocessable();
        $this->assertSame($total, $invoice->fresh()->amount_minor);
        $this->assertSame($beforeLines, $invoice->fresh()->lines);
        $this->assertSame(1, $first->fresh()->redeemed_count);
        $this->assertSame(0, $second->fresh()->redeemed_count);
        $this->assertDatabaseCount('promo_redemptions', 1);
        $this->assertDatabaseMissing('promo_redemptions', ['promo_code_id' => $second->id]);
    }
}
