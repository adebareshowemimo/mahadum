<?php

namespace App\Http\Controllers\Referral;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referral\SendReferralInvitationRequest;
use App\Models\Commission;
use App\Models\Payout;
use App\Models\Referral;
use App\Models\User;
use App\Services\Referral\ReferralAccountExistsException;
use App\Services\Referral\ReferralActivity;
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
            'share_text' => "Learn Yoruba, Igbo, Hausa & English on Mahadum.360 — join with my code {$code->code}.",
        ]]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $code = $this->referrals->codeFor($user);

        $referralsByStatus = Referral::whereHas('referralCode', fn ($q) => $q
            ->where('owner_type', $user->getMorphClass())->where('owner_id', $user->id))
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        $commissions = Commission::where('beneficiary_type', User::class)
            ->where('beneficiary_id', $user->id)
            ->selectRaw('status, COUNT(*) c, COALESCE(SUM(amount_minor),0) total')
            ->groupBy('status')->get()->keyBy('status');

        $clearedMinor = (int) Commission::where('beneficiary_type', User::class)
            ->where('beneficiary_id', $user->id)
            ->where('status', 'cleared')
            ->sum('amount_minor');
        $committedMinor = (int) Payout::where('beneficiary_type', User::class)
            ->where('beneficiary_id', $user->id)
            ->whereIn('status', ['requested', 'approved', 'paid'])
            ->sum('amount_minor');

        return response()->json(['data' => [
            'code' => $code->code,
            'referrals' => $referralsByStatus,
            'commissions' => $commissions,
            'available_minor' => max(0, $clearedMinor - $committedMinor),
        ]]);
    }

    /**
     * The referrer's dashboard: all attributed sign-ups, including pending activation,
     * searchable by the invited contact or the referred user's email / phone.
     */
    public function activations(Request $request, ReferralActivity $activity): JsonResponse
    {
        return response()->json($activity->forOwner(
            $request->user(), (string) $request->query('search', ''), $request->integer('per_page', 20),
        ));
    }

    /** Invites the caller has sent, newest first. */
    public function invitations(Request $request): JsonResponse
    {
        $code = $this->referrals->codeFor($request->user());

        $rows = $code->invitations()->latest('sent_at')->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'channel' => $i->channel,
                'contact' => $i->contact,
                'status' => $i->status,
                'sent_at' => $i->sent_at?->toDateTimeString(),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function invite(SendReferralInvitationRequest $request): JsonResponse
    {
        try {
            $invitation = $this->referrals->invite(
                $request->user(),
                $request->string('channel')->toString(),
                $request->string('contact')->toString(),
            );
        } catch (ReferralAccountExistsException $e) {
            return response()->json([
                'error' => ['code' => 'account_exists', 'message' => $e->getMessage(), 'status' => 422],
            ], 422);
        }

        return response()->json(['data' => [
            'id' => $invitation->id,
            'channel' => $invitation->channel,
            'contact' => $invitation->contact,
            'status' => $invitation->status,
        ]], 201);
    }
}
