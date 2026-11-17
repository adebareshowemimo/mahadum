<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\PurchaseSeatsRequest;
use App\Models\ClassEnrollment;
use App\Models\Invoice;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Services\AuditLogger;
use App\Services\Billing\InvoiceLineBuilder;
use App\Services\Learning\XapiRecorder;
use App\Services\Settings;
use App\Support\SeatPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function inactive(Request $request, Organization $organization, Settings $settings): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);
        $days = (int) $settings->get('school.seat_inactive_days');
        $cutoff = now()->subDays($days);
        $learners = LearnerProfile::where('organization_id', $organization->id)->get(['id', 'user_id', 'display_name', 'created_at']);
        $activity = DB::table('xapi_statements')->whereIn('learner_profile_id', $learners->pluck('id'))
            ->where('verb', '!=', XapiRecorder::VERB_REGISTERED)
            ->selectRaw('learner_profile_id, MAX(created_at) AS last_activity')->groupBy('learner_profile_id')
            ->pluck('last_activity', 'learner_profile_id');
        $rows = $learners->filter(fn ($learner) => ($activity[$learner->id] ?? $learner->created_at) < $cutoff)
            ->map(fn ($learner) => ['learner_id' => $learner->id, 'name' => $learner->display_name,
                'last_activity_at' => $activity[$learner->id] ?? null, 'added_at' => $learner->created_at,
                'classes' => $learner->classEnrollments()->whereHas('schoolClass', fn ($q) => $q->where('organization_id', $organization->id))
                    ->with('schoolClass:id,name')->get()->pluck('schoolClass.name')->unique()->values(),
            ])->values();

        return response()->json(['data' => ['inactive_days' => $days, 'learners' => $rows]]);
    }

    public function release(Request $request, Organization $organization, LearnerProfile $learner, Settings $settings): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);
        abort_unless((int) $learner->organization_id === (int) $organization->id, 404);
        $days = (int) $settings->get('school.seat_inactive_days');

        DB::transaction(function () use ($organization, $learner, $days) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $learner = LearnerProfile::whereKey($learner->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $learner->organization_id === (int) $organization->id, 409, 'This learner no longer holds a school seat.');
            $lastActivity = DB::table('xapi_statements')->where('learner_profile_id', $learner->id)
                ->where('verb', '!=', XapiRecorder::VERB_REGISTERED)->max('created_at') ?? $learner->created_at;
            abort_unless($lastActivity < now()->subDays($days), 422, 'This learner is still active. Review their activity before releasing the seat.');
            $allocation = $organization->seatAllocations()->where('active_filled', '>', 0)->latest('id')->lockForUpdate()->first();
            abort_unless($allocation !== null, 422, 'No filled school seat is available to release.');
            $learner->update(['organization_id' => null]);
            ClassEnrollment::where('learner_profile_id', $learner->id)
                ->whereHas('schoolClass', fn ($query) => $query->where('organization_id', $organization->id))->delete();
            $allocation->decrement('active_filled');
            if ($learner->user_id && ! LearnerProfile::where('user_id', $learner->user_id)
                ->where('organization_id', $organization->id)->exists()) {
                OrganizationUser::where('organization_id', $organization->id)->where('user_id', $learner->user_id)
                    ->where('role', 'student')->delete();
            }
            app(AuditLogger::class)->record('school.seat_released', $learner,
                ['organization_id' => $organization->id, 'allocation_id' => $allocation->id],
                ['organization_id' => null, 'allocation_id' => $allocation->id], $organization->id);
        });

        return response()->json(['data' => ['status' => 'released', 'learner_id' => $learner->id]]);
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

        return DB::transaction(function () use ($request, $organization) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $key = hash('sha256', $request->string('purchase_request_key')->value());
            $fingerprint = hash('sha256', json_encode([$request->integer('quantity'), $request->input('term_label'),
                $request->boolean('auto_renew'), $request->boolean('include_registration', true)], JSON_THROW_ON_ERROR));
            $query = DB::table('school_seat_purchase_requests')->where('organization_id', $organization->id)
                ->where('user_id', $request->user()->id)->where('request_key', $key);
            if ($previous = $query->first()) {
                abort_unless(hash_equals($previous->fingerprint, $fingerprint), 409, 'This purchase reference was already used for different seat details. Start a new purchase.');

                return response()->json(['data' => json_decode($previous->response, true, flags: JSON_THROW_ON_ERROR)], 201)->header('Idempotency-Replayed', 'true');
            }
            // A new browser session has a new transport key. Reuse an equivalent
            // unpaid school obligation rather than allocating and billing twice.
            $matching = DB::table('school_seat_purchase_requests')
                ->where('organization_id', $organization->id)->where('fingerprint', $fingerprint)
                ->latest('id')->get();
            foreach ($matching as $candidate) {
                $existing = json_decode($candidate->response, true, flags: JSON_THROW_ON_ERROR);
                if (isset($existing['invoice_id']) && Invoice::withoutTenancy()->whereKey($existing['invoice_id'])
                    ->where('organization_id', $organization->id)->where('status', 'unpaid')->exists()) {
                    DB::table('school_seat_purchase_requests')->insert(['organization_id' => $organization->id,
                        'user_id' => $request->user()->id, 'request_key' => $key, 'fingerprint' => $fingerprint,
                        'response' => $candidate->response, 'created_at' => now(), 'updated_at' => now()]);

                    return response()->json(['data' => $existing], 201)->header('Idempotency-Replayed', 'true');
                }
            }
            $result = $this->createPurchase($request, $organization);
            DB::table('school_seat_purchase_requests')->insert(['organization_id' => $organization->id, 'user_id' => $request->user()->id,
                'request_key' => $key, 'fingerprint' => $fingerprint, 'response' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(['data' => $result], 201);
        });
    }

    private function createPurchase(PurchaseSeatsRequest $request, Organization $organization): array
    {

        $qty = $request->integer('quantity');
        $band = SeatPricing::bandFor($qty);
        $includeRegistration = $request->boolean('include_registration', true);

        $seatsSubtotal = $qty * $band['per_student_minor'];
        $registration = $includeRegistration ? $band['registration_minor'] : 0;

        $billed = InvoiceLineBuilder::schoolFees($seatsSubtotal, $registration);
        $vat = $billed['total_minor'] - $seatsSubtotal - $registration;

        $allocation = $organization->seatAllocations()->create([
            'total_purchased' => $qty,
            'term_label' => $request->input('term_label'),
            'auto_renew' => $request->boolean('auto_renew'),
            'expires_at' => now()->addMonths(SeatPricing::TERM_MONTHS),
        ]);

        $invoice = $organization->invoices()->create([
            'type' => 'proforma',
            'amount_minor' => $billed['total_minor'],
            'lines' => $billed['lines'],
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);

        $result = [
            'allocation_id' => $allocation->id,
            'quantity' => $qty,
            'band' => $band['label'],
            'per_student_minor' => $band['per_student_minor'],
            'seats_subtotal_minor' => $seatsSubtotal,
            'registration_minor' => $registration,
            'vat_minor' => $vat,
            'amount_minor' => $billed['total_minor'],
            'invoice_id' => $invoice->id,
        ];
        app(AuditLogger::class)->record('school.seats_purchased', $allocation, [], $result, $organization->id);

        return $result;
    }
}
