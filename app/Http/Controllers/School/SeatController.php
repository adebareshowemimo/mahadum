<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\PurchaseSeatsRequest;
use App\Models\Organization;
use App\Support\SeatPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeatController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $allocations = $organization->seatAllocations()->get();

        return response()->json(['data' => [
            'total_purchased' => (int) $allocations->sum('total_purchased'),
            'active_filled' => (int) $allocations->sum('active_filled'),
            'bands' => array_map(fn ($b) => [
                'label' => $b['label'],
                'registration_minor' => $b['registration_minor'],
                'per_student_minor' => $b['per_student_minor'],
            ], SeatPricing::BANDS),
            'allocations' => $allocations->map(fn ($a) => [
                'id' => $a->id,
                'total_purchased' => $a->total_purchased,
                'active_filled' => $a->active_filled,
                'term_label' => $a->term_label,
                'expires_at' => $a->expires_at,
            ])->values(),
        ]]);
    }

    /**
     * Purchase seats for the academic year. Price = per-student band rate ×
     * quantity, plus the band's annual registration fee (which a same-year top-up
     * can opt out of via `include_registration=false`). Generates a proforma
     * invoice for the total.
     */
    public function purchase(PurchaseSeatsRequest $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $qty = $request->integer('quantity');
        $band = SeatPricing::bandFor($qty);
        $includeRegistration = $request->boolean('include_registration', true);

        $seatsSubtotal = $qty * $band['per_student_minor'];
        $registration = $includeRegistration ? $band['registration_minor'] : 0;
        $amount = $seatsSubtotal + $registration;

        $allocation = $organization->seatAllocations()->create([
            'total_purchased' => $qty,
            'term_label' => $request->input('term_label'),
            'auto_renew' => $request->boolean('auto_renew'),
            'expires_at' => now()->addMonths(SeatPricing::TERM_MONTHS),
        ]);

        $invoice = $organization->invoices()->create([
            'type' => 'proforma',
            'amount_minor' => $amount,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);

        return response()->json(['data' => [
            'allocation_id' => $allocation->id,
            'quantity' => $qty,
            'band' => $band['label'],
            'per_student_minor' => $band['per_student_minor'],
            'seats_subtotal_minor' => $seatsSubtotal,
            'registration_minor' => $registration,
            'amount_minor' => $amount,
            'invoice_id' => $invoice->id,
        ]], 201);
    }
}
