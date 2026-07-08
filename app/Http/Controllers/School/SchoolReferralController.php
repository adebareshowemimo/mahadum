<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Referral\RequestPayoutRequest;
use App\Models\Commission;
use App\Models\Organization;
use App\Models\Payout;
use App\Services\Referral\ReferralService;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A school's own referral code (kind 'org') — a school shares this code (not
 * a staff member's personal code) so referred families' commission accrues to
 * the organization, not an individual. Mirrors ReferralController's personal
 * flow, but every balance/payout is scoped to the org as beneficiary.
 */
class SchoolReferralController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private ReferralService $referrals, private Settings $settings) {}

    public function summary(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $code = $this->referrals->codeFor($organization);

        $referralsByStatus = $code->referrals()
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        $commissions = Commission::where('beneficiary_type', Organization::class)
            ->where('beneficiary_id', $organization->id)
            ->selectRaw('status, COUNT(*) c, COALESCE(SUM(amount_minor),0) total')
            ->groupBy('status')->get()->keyBy('status');

        $payouts = $this->payoutsQuery($organization)->latest()
            ->get(['id', 'amount_minor', 'method', 'status', 'requested_at', 'paid_at']);

        return response()->json(['data' => [
            'code' => $code->code,
            'status' => $code->status,
            'share_url' => rtrim(config('app.url'), '/').'/r/'.$code->code,
            'share_text' => "Join {$organization->name} on Mahadum.360 with our school code {$code->code}.",
            'referrals' => $referralsByStatus,
            'commissions' => $commissions,
            'payouts' => $payouts,
        ]]);
    }

    public function requestPayout(RequestPayoutRequest $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $thisMonth = $this->payoutsQuery($organization)
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
        $payout->beneficiary()->associate($organization);
        $payout->save();

        return response()->json(['data' => ['id' => $payout->id, 'status' => $payout->status]], 201);
    }

    /**
     * @return Builder<Payout>
     */
    private function payoutsQuery(Organization $organization): Builder
    {
        return Payout::where('beneficiary_type', Organization::class)->where('beneficiary_id', $organization->id);
    }
}
