<?php

namespace App\Services\Referral;

use App\Models\Referral;
use Illuminate\Database\Eloquent\Model;

class ReferralActivity
{
    public function __construct(private ReferralService $referrals) {}

    /** All attributed sign-ups for this owner, including pending activation. */
    public function forOwner(Model $owner, string $search = '', int $perPage = 20): array
    {
        $query = Referral::whereHas('referralCode', fn ($q) => $q
            ->where('owner_type', $owner->getMorphClass())->where('owner_id', $owner->getKey()))
            ->with(['referredUser:id,email,phone,last_login_at', 'referralCode'])
            ->orderByDesc('signed_up_at')->orderByDesc('id');

        if (trim($search) !== '') {
            $like = '%'.trim($search).'%';
            $query->where(fn ($q) => $q
                ->where('contact_value', 'like', $like)
                ->orWhereHas('referredUser', fn ($u) => $u
                    ->where('email', 'like', $like)->orWhere('phone', 'like', $like)));
        }

        $page = $query->paginate(max(1, min(100, $perPage)));
        $offset = ($page->currentPage() - 1) * $page->perPage();
        $rows = $page->getCollection()->values()->map(function (Referral $referral, int $i) use ($offset) {
            return [
                'sn' => $offset + $i + 1,
                'activated_at' => $referral->activated_at?->toDateString(),
                'code' => $referral->referralCode->code,
                'via_email' => ($referral->contact_channel === 'email' ? $referral->contact_value : null) ?: $referral->referredUser?->email,
                'via_phone' => ($referral->contact_channel === 'phone' ? $referral->contact_value : null) ?: $referral->referredUser?->phone,
                'status' => $referral->activated_at === null ? 'pending'
                    : ($this->referrals->isReferredUserActive($referral) ? 'active' : 'inactive'),
            ];
        });

        return [
            'data' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }
}
