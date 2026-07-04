<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\RequestTeacherCompensationPayoutRequest;
use App\Models\Payout;
use App\Models\TeacherCompensationEntry;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cash compensation a teacher accrues for currently-seated students in their
 * classes (`compensation:accrue-teachers`, monthly) — distinct from, and paid
 * out separately to, the referral-commission system (Payout.source
 * differentiates the two pools; see PayoutController for the referral path).
 */
class TeacherCompensationController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $entries = TeacherCompensationEntry::where('teacher_user_id', $userId)->get();
        $accruedTotal = (int) $entries->sum('amount_minor');
        $paidOut = (int) $this->payoutsQuery($userId)
            ->whereIn('status', ['requested', 'approved', 'paid'])
            ->sum('amount_minor');

        return response()->json(['data' => [
            'available_minor' => max(0, $accruedTotal - $paidOut),
            'accrued_total_minor' => $accruedTotal,
            'months' => $entries->sortByDesc('period')->values()->map(fn (TeacherCompensationEntry $e) => [
                'period' => $e->period,
                'paying_student_count' => $e->paying_student_count,
                'rate_minor' => $e->rate_minor,
                'amount_minor' => $e->amount_minor,
            ]),
        ]]);
    }

    public function requestPayout(RequestTeacherCompensationPayoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $amount = $request->integer('amount_minor');

        $accruedTotal = (int) TeacherCompensationEntry::where('teacher_user_id', $user->id)->sum('amount_minor');
        $paidOut = (int) $this->payoutsQuery($user->id)
            ->whereIn('status', ['requested', 'approved', 'paid'])
            ->sum('amount_minor');
        $available = max(0, $accruedTotal - $paidOut);

        if ($amount > $available) {
            return response()->json([
                'error' => ['code' => 'insufficient_balance', 'message' => 'Amount exceeds your available teaching compensation balance.', 'status' => 422],
            ], 422);
        }

        $capMinor = (int) $this->settings->get('referral.payout_cap_minor', 5_000_000);
        $thisMonth = (int) $this->payoutsQuery($user->id)
            ->whereIn('status', ['requested', 'approved', 'paid'])
            ->where('requested_at', '>=', now()->startOfMonth())
            ->sum('amount_minor');

        if ($thisMonth + $amount > $capMinor) {
            return response()->json([
                'error' => ['code' => 'payout_cap_exceeded', 'message' => 'Monthly payout cap (₦'.number_format($capMinor / 100).') exceeded.', 'status' => 422],
            ], 422);
        }

        $payout = new Payout([
            'amount_minor' => $amount,
            'method' => 'bank',
            'source' => 'teaching',
            'status' => 'requested',
            'requested_at' => now(),
        ]);
        $payout->beneficiary()->associate($user);
        $payout->save();

        return response()->json(['data' => ['id' => $payout->id, 'status' => $payout->status]], 201);
    }

    /**
     * @return Builder<Payout>
     */
    private function payoutsQuery(int $userId): Builder
    {
        return Payout::where('beneficiary_type', User::class)
            ->where('beneficiary_id', $userId)
            ->where('source', 'teaching');
    }
}
