<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SeatPurchaseRetryTest extends TestCase
{
    use RefreshDatabase;

    private function school(): Organization
    {
        $this->seedRbac();
        $school = Organization::create(['name' => 'Retry School', 'slug' => 'retry-school', 'type' => 'school', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin')->fresh();
        $school->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $this->actingAsUser($admin);

        return $school;
    }

    public function test_retry_replays_original_invoice_and_term_even_after_cache_expiry(): void
    {
        $school = $this->school();
        $url = "/api/v1/schools/{$school->id}/seats/purchase";
        $input = ['quantity' => 100, 'term_label' => '2026/27', 'include_registration' => true];
        $headers = ['Idempotency-Key' => 'same-seat-purchase'];
        $original = $this->postJson($url, $input, $headers)->assertCreated()->json('data');
        $expiry = $school->seatAllocations()->sole()->expires_at->toIso8601String();
        Cache::flush();
        $this->travel(8)->days();
        $this->postJson($url, $input, $headers)->assertCreated()->assertHeader('Idempotency-Replayed', 'true')->assertExactJson(['data' => $original]);
        $this->assertSame($expiry, $school->seatAllocations()->sole()->expires_at->toIso8601String());
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('seat_allocations', 1);
        $this->assertDatabaseCount('school_seat_purchase_requests', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'school.seats_purchased']);
    }

    public function test_changed_details_cannot_reuse_a_key_and_intentional_new_purchase_stays_distinct(): void
    {
        $school = $this->school();
        $url = "/api/v1/schools/{$school->id}/seats/purchase";
        $this->postJson($url, ['quantity' => 100], ['Idempotency-Key' => 'purchase-first'])->assertCreated();
        $this->postJson($url, ['quantity' => 101], ['Idempotency-Key' => 'purchase-first'])->assertConflict();
        $this->assertDatabaseCount('invoices', 1);
        $this->postJson($url, ['quantity' => 100], ['Idempotency-Key' => 'purchase-second'])->assertCreated();
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('seat_allocations', 2);
    }

    public function test_missing_key_or_foreign_school_cannot_create_an_obligation(): void
    {
        $school = $this->school();
        $url = "/api/v1/schools/{$school->id}/seats/purchase";
        $this->postJson($url, ['quantity' => 100])->assertUnprocessable();
        $this->actingAsUser($this->userWithRole('school_admin')->fresh());
        $this->postJson($url, ['quantity' => 100], ['Idempotency-Key' => 'foreign-purchase'])->assertForbidden();
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('seat_allocations', 0);
    }

    public function test_failure_rolls_back_both_invoice_and_allocation(): void
    {
        $school = $this->school();
        $this->mock(AuditLogger::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Simulated persistence failure'));
        $this->postJson("/api/v1/schools/{$school->id}/seats/purchase", ['quantity' => 100], ['Idempotency-Key' => 'failed-purchase'])->assertStatus(500);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('seat_allocations', 0);
        $this->assertDatabaseCount('school_seat_purchase_requests', 0);
    }
}
