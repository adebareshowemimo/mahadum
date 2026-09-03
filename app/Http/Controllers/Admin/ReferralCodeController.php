<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin referral dashboard: per referral code, how many people activated
 * it, the via-email / via-phone split, and how many of those referred people are
 * currently active vs inactive.
 */
class ReferralCodeController extends Controller
{
    public function __construct(private ReferralService $referrals) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = ReferralCode::query()
            ->with('owner')
            ->withCount([
                'referrals as activated_count' => fn ($q) => $q->whereNotNull('activated_at'),
                'referrals as via_email_count' => fn ($q) => $q->whereNotNull('activated_at')->where('contact_channel', 'email'),
                'referrals as via_phone_count' => fn ($q) => $q->whereNotNull('activated_at')->where('contact_channel', 'phone'),
            ])
            ->orderByDesc('activated_count');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(fn ($q) => $q->where('code', 'like', $like)
                ->orWhereHasMorph('owner', [User::class, Organization::class],
                    fn ($o) => $o->where('name', 'like', $like)));
        }

        $page = $query->paginate((int) $request->integer('per_page', 25));

        $offset = ($page->currentPage() - 1) * $page->perPage();
        $rows = $page->getCollection()->values()->map(function (ReferralCode $code, int $i) use ($offset) {
            [$active, $inactive] = $this->activeSplit($code);

            return [
                'sn' => $offset + $i + 1,
                'id' => $code->id,
                'code' => $code->code,
                'owner' => [
                    'type' => $code->owner_type === User::class ? 'user' : 'organization',
                    'id' => $code->owner_id,
                    'name' => $code->owner->getAttribute('name'),
                ],
                'status' => $code->status,
                'count_activated' => (int) $code->getAttribute('activated_count'),
                'via_email' => (int) $code->getAttribute('via_email_count'),
                'via_phone' => (int) $code->getAttribute('via_phone_count'),
                'active_count' => $active,
                'inactive_count' => $inactive,
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

    /**
     * @return array{0: int, 1: int} [active, inactive] among this code's activated referrals
     */
    private function activeSplit(ReferralCode $code): array
    {
        $activated = $code->referrals()->whereNotNull('activated_at')
            ->with('referredUser:id,email,phone,last_login_at')->get();

        $active = $activated->filter(fn (Referral $r) => $this->referrals->isReferredUserActive($r))->count();

        return [$active, $activated->count() - $active];
    }
}
