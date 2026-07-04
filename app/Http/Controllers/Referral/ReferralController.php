<?php

namespace App\Http\Controllers\Referral;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(private ReferralService $referrals) {}

    public function code(Request $request): JsonResponse
    {
        $code = $this->referrals->codeFor($request->user());

        return response()->json(['data' => [
            'code' => $code->code,
            'status' => $code->status,
            'share_url' => rtrim(config('app.url'), '/').'/r/'.$code->code,
            'share_text' => "Learn Yoruba, Igbo, Hausa & Pidgin on Mahadum.360 — join with my code {$code->code}.",
        ]]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $code = $this->referrals->codeFor($user);

        $referralsByStatus = $code->referrals()
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        $commissions = Commission::where('beneficiary_type', User::class)
            ->where('beneficiary_id', $user->id)
            ->selectRaw('status, COUNT(*) c, COALESCE(SUM(amount_minor),0) total')
            ->groupBy('status')->get()->keyBy('status');

        return response()->json(['data' => [
            'code' => $code->code,
            'referrals' => $referralsByStatus,
            'commissions' => $commissions,
        ]]);
    }
}
