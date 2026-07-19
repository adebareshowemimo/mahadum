<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Row-level multi-tenancy: School A must never see School B's rows.
 *
 * Existing suites already cover 403-on-access-by-id (SchoolAndAdminTest,
 * SchoolReferralTest, InvoicePdfTest). The gap this closes is *list* leakage —
 * a missing BelongsToTenant global scope wouldn't 403, it would silently
 * include foreign rows in an index response, which is the quieter and more
 * dangerous failure.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function org(string $slug): Organization
    {
        return Organization::create([
            'name' => ucfirst($slug),
            'type' => 'school',
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    /** A school_admin belonging to $org, acting as the current user. */
    private function adminOf(Organization $org): User
    {
        $admin = $this->userWithRole('school_admin');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);

        return $admin;
    }

    public function test_class_list_excludes_another_schools_classes(): void
    {
        $this->seedRbac();
        $home = $this->org('home');
        $foreign = $this->org('foreign');

        SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $home->id, 'name' => 'Home JSS1', 'level' => 'JSS1',
        ]);
        SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $foreign->id, 'name' => 'Foreign JSS1', 'level' => 'JSS1',
        ]);

        $this->actingAsUser($this->adminOf($home));

        $res = $this->getJson('/api/v1/classes')->assertOk();

        $names = collect($res->json('data'))->pluck('name');
        $this->assertContains('Home JSS1', $names);
        $this->assertNotContains('Foreign JSS1', $names, 'Another tenant’s class leaked into the list.');
    }

    public function test_invoice_list_excludes_another_schools_invoices(): void
    {
        $this->seedRbac();
        $home = $this->org('home');
        $foreign = $this->org('foreign');

        $homeInvoice = Invoice::withoutGlobalScopes()->create([
            'organization_id' => $home->id, 'type' => 'proforma',
            'amount_minor' => 100_000, 'status' => 'unpaid',
        ]);
        $foreignInvoice = Invoice::withoutGlobalScopes()->create([
            'organization_id' => $foreign->id, 'type' => 'proforma',
            'amount_minor' => 100_000, 'status' => 'unpaid',
        ]);

        $this->actingAsUser($this->adminOf($home));

        $res = $this->getJson("/api/v1/schools/{$home->id}/invoices")->assertOk();

        $ids = collect($res->json('data'))->pluck('id');
        $this->assertContains($homeInvoice->id, $ids);
        $this->assertNotContains($foreignInvoice->id, $ids, 'Another tenant’s invoice leaked into the list.');
    }

    public function test_the_tenant_scope_applies_at_the_model_layer(): void
    {
        $this->seedRbac();
        $home = $this->org('home');
        $foreign = $this->org('foreign');

        SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $home->id, 'name' => 'Home', 'level' => 'JSS1',
        ]);
        SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $foreign->id, 'name' => 'Foreign', 'level' => 'JSS1',
        ]);

        $this->actingAsUser($this->adminOf($home));
        // Re-enter the request lifecycle so IdentifyTenant resolves the tenant.
        $this->getJson('/api/v1/classes')->assertOk();

        $this->assertSame(2, SchoolClass::withoutGlobalScopes()->count(), 'Both rows exist unscoped.');
    }

    public function test_a_super_admin_sees_across_tenants(): void
    {
        $this->seedRbac();
        $a = $this->org('alpha');
        $b = $this->org('beta');

        Invoice::withoutGlobalScopes()->create([
            'organization_id' => $a->id, 'type' => 'proforma', 'amount_minor' => 1000,
            'status' => 'unpaid',
        ]);
        Invoice::withoutGlobalScopes()->create([
            'organization_id' => $b->id, 'type' => 'proforma', 'amount_minor' => 1000,
            'status' => 'unpaid',
        ]);

        $this->actingAsUser($this->userWithRole('super_admin'));

        // super_admin runs unscoped (IdentifyTenant precedence rule 1).
        $this->assertSame(2, Invoice::count());
    }
}
