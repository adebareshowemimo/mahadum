<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\SeatAllocation;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelDuplicateInvoice extends Command
{
    protected $signature = 'billing:cancel-duplicate-invoice {original} {duplicate} {--execute}';

    protected $description = 'Verify two identical unpaid school invoices and cancel the duplicate with its unused seat allocation';

    public function handle(AuditLogger $audit): int
    {
        try {
            return DB::transaction(function () use ($audit): int {
                $original = Invoice::withoutTenancy()->whereKey($this->argument('original'))->lockForUpdate()->firstOrFail();
                $duplicate = Invoice::withoutTenancy()->whereKey($this->argument('duplicate'))->lockForUpdate()->firstOrFail();
                $same = $original->id !== $duplicate->id
                    && $original->organization_id === $duplicate->organization_id
                    && $original->type === $duplicate->type
                    && $original->amount_minor === $duplicate->amount_minor
                    && $original->breakdownLines() === $duplicate->breakdownLines();
                if (! $same || $original->status !== 'unpaid' || $duplicate->status !== 'unpaid'
                    || $duplicate->gateway_txn_ref !== null || $duplicate->checkout_url !== null
                    || $duplicate->paid_at !== null) {
                    throw new RuntimeException('Invoices differ, are not both unpaid, or duplicate checkout/payment has started. Nothing changed.');
                }

                $requests = DB::table('school_seat_purchase_requests')
                    ->where('organization_id', $original->organization_id)->lockForUpdate()->get();
                $source = $requests->first(fn ($request) => (json_decode($request->response, true)['invoice_id'] ?? null) === $original->id);
                $duplicates = $requests->filter(fn ($request) => (json_decode($request->response, true)['invoice_id'] ?? null) === $duplicate->id);
                if (! $source || $duplicates->isEmpty()
                    || $duplicates->contains(fn ($request) => $request->fingerprint !== $source->fingerprint)) {
                    throw new RuntimeException('Matching seat purchase records were not found. Inspect the allocation manually; nothing changed.');
                }
                $sourceData = json_decode($source->response, true, flags: JSON_THROW_ON_ERROR);
                $duplicateData = json_decode($duplicates->first()->response, true, flags: JSON_THROW_ON_ERROR);
                $allocation = SeatAllocation::withoutTenancy()->whereKey($duplicateData['allocation_id'] ?? 0)
                    ->where('organization_id', $original->organization_id)->lockForUpdate()->first();
                if (! $allocation || $allocation->active_filled !== 0
                    || $sourceData['allocation_id'] === $duplicateData['allocation_id']) {
                    throw new RuntimeException('Duplicate seats are missing, in use, or linked to the original. Nothing changed.');
                }

                $this->line("School {$original->organization_id}: keep #{$original->id}; cancel #{$duplicate->id} (".number_format($duplicate->amount_minor / 100, 2).' NGN); remove unused seat allocation #'.$allocation->id.'.');
                if (! $this->option('execute')) {
                    $this->info('Preview only. Add --execute after verifying these production records.');

                    return self::SUCCESS;
                }

                $duplicate->update(['status' => 'cancelled']);
                $audit->record('invoice.duplicate_cancelled', $duplicate, ['status' => 'unpaid'],
                    ['status' => 'cancelled', 'original_invoice_id' => $original->id, 'removed_allocation_id' => $allocation->id],
                    $original->organization_id);
                $allocation->delete();
                DB::table('school_seat_purchase_requests')->whereIn('id', $duplicates->pluck('id'))
                    ->update(['response' => $source->response, 'updated_at' => now()]);
                $this->info('Duplicate invoice cancelled; unused seats removed; retries now point to the original invoice.');

                return self::SUCCESS;
            });
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
