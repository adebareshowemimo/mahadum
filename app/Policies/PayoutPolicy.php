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
        return $user->can('payouts.view') && $payout->isBeneficiary($user);
    }

    public function create(User $user): bool
    {
        return $user->can('payouts.request');
    }

    public function approve(User $user, Payout $payout): bool
    {
        // Separation of duties: never let a beneficiary approve their own payout.
        // NB: payouts.approve is a super_admin-only permission, and the
        // super_admin Gate::before bypass short-circuits this policy entirely —
        // so the real SoD enforcement lives in PayoutController::approve, which
        // runs regardless of the gate. This guard covers any future role that
        // is granted payouts.approve without the super_admin bypass.
        return $user->can('payouts.approve') && ! $payout->isBeneficiary($user);
    }
}
