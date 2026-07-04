<?php

namespace App\Policies;

use App\Models\Payout;
use App\Models\User;

/**
 * Payouts have a polymorphic beneficiary (user | organization). A requester may
 * only see/raise payouts for a beneficiary they own; approval is settlement-grade
 * and effectively super_admin (payouts.approve is granted to no other role).
 */
class PayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payouts.view');
    }

    public function view(User $user, Payout $payout): bool
    {
        return $user->can('payouts.view') && $this->ownsBeneficiary($user, $payout);
    }

    public function create(User $user): bool
    {
        return $user->can('payouts.request');
    }

    public function approve(User $user, Payout $payout): bool
    {
        // Separation of duties: never let a requester approve their own payout.
        return $user->can('payouts.approve') && ! $this->ownsBeneficiary($user, $payout);
    }

    private function ownsBeneficiary(User $user, Payout $payout): bool
    {
        if ($payout->beneficiary_type === User::class) {
            return (int) $payout->beneficiary_id === (int) $user->id;
        }

        // Organization beneficiary → user must belong to that org.
        return $user->organizations()
            ->wherePivot('status', 'active')
            ->whereKey($payout->beneficiary_id)
            ->exists();
    }
}
