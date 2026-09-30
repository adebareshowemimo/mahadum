<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\PromoCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private function orgWithAdmin(): Organization
    {
        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $this->actingAsUser($admin);

        return $org;
    }

    public function test_invoice_promo_updates_total_and_cannot_stack(): void
    {
        $this->seedRbac();
        $org = $this->orgWithAdmin();
        $invoice = $org->invoices()->create(['type' => 'final', 'amount_minor' => 500000, 'status' => 'unpaid']);
        PromoCode::create(['code' => 'SCHOOL20', 'discount_type' => 'percent', 'value' => 20, 'applicable_tier' => 'school']);
        $url = "/api/v1/schools/{$org->id}/invoices/{$invoice->id}/promo";
        $this->postJson($url, ['code' => 'bad'])->assertUnprocessable();
        $this->postJson($url, ['code' => 'school20'])->assertOk()->assertJsonPath('data.amount_minor', 400000);
        $this->assertEquals(400000, $invoice->fresh()->amount_minor);
        $this->postJson($url, ['code' => 'SCHOOL20'])->assertUnprocessable();
        $this->assertDatabaseHas('promo_codes', ['code' => 'SCHOOL20', 'redeemed_count' => 1]);
    }

    public function test_full_discount_can_be_settled_without_gateway_payment(): void
    {
        $this->seedRbac();
        Notification::fake();
        $org = $this->orgWithAdmin();
        $invoice = $org->invoices()->create(['type' => 'final', 'amount_minor' => 500000, 'status' => 'unpaid']);
        PromoCode::create(['code' => 'FREE100', 'discount_type' => 'percent', 'value' => 100]);
        $url = "/api/v1/schools/{$org->id}/invoices/{$invoice->id}";
        $this->postJson($url.'/promo', ['code' => 'FREE100'])->assertOk()->assertJsonPath('data.amount_minor', 0);
        $this->postJson($url.'/pay')->assertOk()->assertJsonPath('data.settled', true);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_admin_downloads_a_generated_invoice_pdf(): void
    {
        $this->seedRbac();
        Storage::fake('local');
        $org = $this->orgWithAdmin();
        $invoice = $org->invoices()->create(['type' => 'final', 'amount_minor' => 500000, 'status' => 'unpaid', 'issued_at' => now()]);

        $this->get("/api/v1/schools/{$org->id}/invoices/{$invoice->id}/pdf")
            ->assertOk()
            ->assertDownload("invoice-{$invoice->id}.pdf");

        $this->assertNotNull($invoice->fresh()->pdf_asset_id);
        $this->assertDatabaseHas('media_assets', ['type' => 'pdf']);
        Storage::disk('local')->assertExists("invoices/invoice-{$invoice->id}.pdf");
    }

    public function test_pdf_is_generated_once_and_reused(): void
    {
        $this->seedRbac();
        Storage::fake('local');
        $org = $this->orgWithAdmin();
        $invoice = $org->invoices()->create(['type' => 'final', 'amount_minor' => 500000, 'status' => 'unpaid']);

        $this->get("/api/v1/schools/{$org->id}/invoices/{$invoice->id}/pdf")->assertOk();
        $this->get("/api/v1/schools/{$org->id}/invoices/{$invoice->id}/pdf")->assertOk();

        $this->assertSame(1, MediaAsset::where('type', 'pdf')->count());
    }

    public function test_pdf_is_regenerated_if_the_stored_file_went_missing(): void
    {
        // Reproduces the live-review report: "Could not download that invoice" —
        // the DB still had pdf_asset_id set from an earlier render, but the file
        // itself was gone from disk (e.g. an ephemeral deploy wiped local storage).
        $this->seedRbac();
        Storage::fake('local');
        $org = $this->orgWithAdmin();
        $invoice = $org->invoices()->create(['type' => 'final', 'amount_minor' => 500000, 'status' => 'unpaid']);

        $this->get("/api/v1/schools/{$org->id}/invoices/{$invoice->id}/pdf")->assertOk();
        $staleAssetId = $invoice->fresh()->pdf_asset_id;
        Storage::disk('local')->delete("invoices/invoice-{$invoice->id}.pdf");

        $this->get("/api/v1/schools/{$org->id}/invoices/{$invoice->id}/pdf")
            ->assertOk()
            ->assertDownload("invoice-{$invoice->id}.pdf");

        Storage::disk('local')->assertExists("invoices/invoice-{$invoice->id}.pdf");
        $this->assertNotSame($staleAssetId, $invoice->fresh()->pdf_asset_id);
    }

    public function test_non_member_cannot_download_invoice(): void
    {
        $this->seedRbac();
        $org = Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
        $invoice = $org->invoices()->create(['type' => 'final', 'amount_minor' => 500000, 'status' => 'unpaid']);

        // An unrelated school_admin (not a member of this org).
        $this->actingAsUser($this->userWithRole('school_admin'));

        $this->get("/api/v1/schools/{$org->id}/invoices/{$invoice->id}/pdf")->assertForbidden();
    }
}
