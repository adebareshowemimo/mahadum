<?php

namespace App\Http\Controllers\Referral;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referral\SendReferralInvitationRequest;
use App\Models\Commission;
use App\Models\Payout;
use App\Models\Referral;
use App\Models\User;
use App\Services\Referral\ReferralAccountExistsException;
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

        $referralsByStatus = $code->referrals()
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
     * The referrer's dashboard: everyone who activated their code, newest first,
     * searchable by the invited contact or the referred user's email / phone.
     */
    public function activations(Request $request): JsonResponse
    {
        $code = $this->referrals->codeFor($request->user());
        $search = trim((string) $request->query('search', ''));

        $query = $code->referrals()
            ->whereNotNull('activated_at')
            ->with('referredUser:id,email,phone')
            ->orderByDesc('activated_at');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(fn ($q) => $q
                ->where('contact_value', 'like', $like)
                ->orWhereHas('referredUser', fn ($u) => $u
                    ->where('email', 'like', $like)->orWhere('phone', 'like', $like)));
        }

        $page = $query->paginate((int) $request->integer('per_page', 20));

        $offset = ($page->currentPage() - 1) * $page->perPage();
        $rows = $page->getCollection()->values()->map(function (Referral $referral, int $i) use ($offset, $code) {
            $email = $referral->contact_channel === 'email' ? $referral->contact_value : $referral->referredUser?->email;
            $phone = $referral->contact_channel === 'phone' ? $referral->contact_value : $referral->referredUser?->phone;

            return [
                'sn' => $offset + $i + 1,
                'activated_at' => $referral->activated_at?->toDateString(),
                'code' => $code->code,
                'via_email' => $referral->contact_channel === 'phone' ? null : $email,
                'via_phone' => $referral->contact_channel === 'email' ? null : $phone,
                'status' => $this->referrals->isReferredUserActive($referral) ? 'active' : 'inactive',
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
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
