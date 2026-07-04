<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
