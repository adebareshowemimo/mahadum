<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralCode;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * Referral fraud review. The FR-7.5 velocity guard flags codes with abnormal
 * 24h sign-up spikes; this surfaces flagged/frozen codes for a human to clear
 * (release) or freeze (confirm fraud — stops future qualifying referrals).
 */
class FraudController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $codes = ReferralCode::query()
            ->with('owner')
            ->whereIn('status', ['flagged', 'frozen'])
            ->withCount([
                'referrals',
                'referrals as referrals_24h_count' => fn ($q) => $q->where('signed_up_at', '>=', now()->subDay()),
            ])
            ->latest('updated_at')
            ->get()
            ->map(fn (ReferralCode $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'kind' => $c->kind,
                'status' => $c->status,
                'owner' => [
                    'type' => $c->owner_type === User::class ? 'user' : 'organization',
                    'id' => $c->owner_id,
                    'name' => $c->owner->getAttribute('name'),
                ],
                'referrals_total' => $c->referrals_count,
                'referrals_24h' => $c->getAttribute('referrals_24h_count'),
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $codes]);
    }

    /** Release a code back to active (false positive / resolved). */
    public function clear(ReferralCode $referralCode): JsonResponse
    {
        return $this->transition($referralCode, 'active', 'referral.cleared');
    }

    /** Confirm fraud — freeze the code so it stops qualifying new referrals. */
    public function freeze(ReferralCode $referralCode): JsonResponse
    {
        return $this->transition($referralCode, 'frozen', 'referral.frozen');
    }

    private function transition(ReferralCode $code, string $status, string $action): JsonResponse
    {
        $before = $code->status;
        $code->update(['status' => $status]);

        $this->audit->record($action, $code, ['status' => $before], ['status' => $status]);

        return response()->json(['data' => ['id' => $code->id, 'status' => $code->status]]);
    }
}
