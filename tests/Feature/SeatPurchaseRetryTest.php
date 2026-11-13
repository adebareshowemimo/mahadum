<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\SeatAllocation;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    public function test_changed_details_cannot_reuse_a_key_and_new_key_reuses_identical_unpaid_invoice(): void
    {
        $school = $this->school();
        $url = "/api/v1/schools/{$school->id}/seats/purchase";
        $this->postJson($url, ['quantity' => 100], ['Idempotency-Key' => 'purchase-first'])->assertCreated();
        $this->postJson($url, ['quantity' => 101], ['Idempotency-Key' => 'purchase-first'])->assertConflict();
        $this->assertDatabaseCount('invoices', 1);
        $this->postJson($url, ['quantity' => 100], ['Idempotency-Key' => 'purchase-second'])
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('seat_allocations', 1);
        $this->assertDatabaseCount('school_seat_purchase_requests', 2);
    }

    public function test_a_new_purchase_is_allowed_after_the_first_invoice_is_paid(): void
    {
        $school = $this->school();
        $url = "/api/v1/schools/{$school->id}/seats/purchase";
        $first = $this->postJson($url, ['quantity' => 100], ['Idempotency-Key' => 'purchase-first'])->assertCreated()->json('data');
        $school->invoices()->findOrFail($first['invoice_id'])->update(['status' => 'paid', 'paid_at' => now()]);
        $second = $this->postJson($url, ['quantity' => 100], ['Idempotency-Key' => 'purchase-second'])->assertCreated()->json('data');
        $this->assertNotSame($first['invoice_id'], $second['invoice_id']);
        $this->assertDatabaseCount('invoices', 2);
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

    public function test_duplicate_invoice_cleanup_previews_then_cancels_only_identical_unpaid_unused_purchase(): void
    {
        $school = $this->school();
        $original = $this->postJson("/api/v1/schools/{$school->id}/seats/purchase", ['quantity' => 100],
            ['Idempotency-Key' => 'purchase-original'])->assertCreated()->json('data');
        $originalInvoice = Invoice::findOrFail($original['invoice_id']);
        $duplicateInvoice = $originalInvoice->replicate();
        $duplicateInvoice->save();
        $allocation = SeatAllocation::findOrFail($original['allocation_id'])->replicate();
        $allocation->save();
        $duplicate = $original;
        $duplicate['invoice_id'] = $duplicateInvoice->id;
        $duplicate['allocation_id'] = $allocation->id;
        $source = DB::table('school_seat_purchase_requests')->first();
        DB::table('school_seat_purchase_requests')->insert([
            'organization_id' => $school->id, 'user_id' => $source->user_id,
            'request_key' => hash('sha256', 'duplicate'), 'fingerprint' => $source->fingerprint,
            'response' => json_encode($duplicate, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('billing:cancel-duplicate-invoice', ['original' => $originalInvoice->id,
            'duplicate' => $duplicateInvoice->id])->assertSuccessful();
        $this->assertSame('unpaid', $duplicateInvoice->fresh()->status);
        $this->artisan('billing:cancel-duplicate-invoice', ['original' => $originalInvoice->id,
            'duplicate' => $duplicateInvoice->id, '--execute' => true])->assertSuccessful();
        $this->assertSame('cancelled', $duplicateInvoice->fresh()->status);
        $this->assertDatabaseMissing('seat_allocations', ['id' => $allocation->id]);
        $this->assertSame(1, $school->invoices()->where('status', 'unpaid')->count());
        $this->getJson("/api/v1/schools/{$school->id}/invoices")->assertOk()->assertJsonCount(1, 'data');
        $replayed = json_decode(DB::table('school_seat_purchase_requests')->where('request_key', hash('sha256', 'duplicate'))->value('response'), true);
        $this->assertSame($originalInvoice->id, $replayed['invoice_id']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.duplicate_cancelled']);
    }

    public function test_duplicate_cleanup_refuses_an_invoice_with_checkout_started(): void
    {
        $school = $this->school();
        $first = $this->postJson("/api/v1/schools/{$school->id}/seats/purchase", ['quantity' => 100],
            ['Idempotency-Key' => 'original-checkout'])->assertCreated()->json('data');
        $original = Invoice::findOrFail($first['invoice_id']);
        $duplicate = $original->replicate();
        $duplicate->gateway_txn_ref = 'gateway-started';
        $duplicate->save();

        $this->artisan('billing:cancel-duplicate-invoice', [
            'original' => $original->id, 'duplicate' => $duplicate->id, '--execute' => true,
        ])->assertFailed();
        $this->assertSame('unpaid', $duplicate->fresh()->status);
    }
}
