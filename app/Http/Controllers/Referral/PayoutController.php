<?php

namespace App\Http\Controllers\Referral;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referral\RequestPayoutRequest;
use App\Models\Payout;
use App\Models\User;
use App\Notifications\PayoutApproved;
use App\Services\AuditLogger;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function __construct(private AuditLogger $audit, private Settings $settings) {}

    /** Own payouts (or all for super_admin). Route guards viewAny via policy. */
    public function index(Request $request): JsonResponse
    {
        $query = Payout::query()->latest();

        if (! $request->user()->hasRole('super_admin')) {
            $query->where('beneficiary_type', User::class)->where('beneficiary_id', $request->user()->id);
        }

        return response()->json(['data' => $query->get(['id', 'amount_minor', 'method', 'source', 'status', 'requested_at', 'paid_at'])]);
    }

    /**
     * Super-admin approval queue: every payout (optionally filtered by status),
     * with a resolved beneficiary label so the reviewer sees who gets paid.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Payout::query()->with('beneficiary')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $page = $query->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(function (Payout $p) {
                // beneficiary is a morphTo (User|Organization) — both expose a `name`.
                $name = $p->beneficiary->getAttribute('name');

                return [
                    'id' => $p->id,
                    'amount_minor' => $p->amount_minor,
                    'method' => $p->method,
                    'source' => $p->source,
                    'status' => $p->status,
                    'requested_at' => $p->requested_at?->toIso8601String(),
                    'paid_at' => $p->paid_at?->toIso8601String(),
                    'approved_by' => $p->approved_by,
                    'beneficiary' => [
                        'type' => $p->beneficiary_type === User::class ? 'user' : 'organization',
                        'id' => $p->beneficiary_id,
                        'name' => $name,
                    ],
                ];
            }),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(RequestPayoutRequest $request): JsonResponse
    {
        $user = $request->user();

        $thisMonth = Payout::where('beneficiary_type', User::class)
            ->where('beneficiary_id', $user->id)
            ->whereIn('status', ['requested', 'approved', 'paid'])
            ->where('requested_at', '>=', now()->startOfMonth())
            ->sum('amount_minor');

        $capMinor = (int) $this->settings->get('referral.payout_cap_minor', 5_000_000);

        if ($thisMonth + $request->integer('amount_minor') > $capMinor) {
            return response()->json([
                'error' => ['code' => 'payout_cap_exceeded', 'message' => 'Monthly payout cap (₦'.number_format($capMinor / 100).') exceeded.', 'status' => 422],
            ], 422);
        }

        $payout = new Payout([
            'amount_minor' => $request->integer('amount_minor'),
            'method' => $request->string('method'),
            'status' => 'requested',
            'requested_at' => now(),
        ]);
        $payout->beneficiary()->associate($user);
        $payout->save();

        return response()->json(['data' => ['id' => $payout->id, 'status' => $payout->status]], 201);
    }

    /** Approve a payout (super_admin). Separation of duties enforced here. */
    public function approve(Request $request, Payout $payout): JsonResponse
    {
        $approver = $request->user();

        // Separation of duties — enforced HERE, not only in PayoutPolicy::approve.
        // payouts.approve is a super_admin-only permission, and the super_admin
        // Gate::before bypass short-circuits the policy, so the policy's
        // approver≠beneficiary guard never actually runs for the role that can
        // reach this endpoint. This check does, regardless of the gate.
        if ($payout->isBeneficiary($approver)) {
            return response()->json([
                'error' => ['code' => 'payout_self_approval', 'message' => 'You cannot approve a payout you would receive.', 'status' => 403],
            ], 403);
        }

        if ($payout->status !== 'requested') {
            return response()->json([
                'error' => ['code' => 'payout_not_pending', 'message' => 'Only a requested payout can be approved.', 'status' => 409],
            ], 409);
        }

        $payout->update(['status' => 'approved', 'approved_by' => $approver->id]);

        $this->audit->record('payout.approved', $payout, ['status' => 'requested'], ['status' => 'approved', 'amount_minor' => $payout->amount_minor]);

        if ($payout->beneficiary instanceof User) {
            $payout->beneficiary->notify(new PayoutApproved($payout));
        }

        return response()->json(['data' => ['id' => $payout->id, 'status' => $payout->status, 'approved_by' => $payout->approved_by]]);
    }

    /** Reject a requested payout (super_admin). Only a pending request can be rejected. */
    public function reject(Request $request, Payout $payout): JsonResponse
    {
        if ($payout->status !== 'requested') {
            return response()->json([
                'error' => ['code' => 'payout_not_pending', 'message' => 'Only a requested payout can be rejected.', 'status' => 409],
            ], 409);
        }

        $payout->update(['status' => 'rejected']);

        $this->audit->record('payout.rejected', $payout, ['status' => 'requested'], ['status' => 'rejected', 'amount_minor' => $payout->amount_minor]);

        return response()->json(['data' => ['id' => $payout->id, 'status' => $payout->status]]);
    }
}
