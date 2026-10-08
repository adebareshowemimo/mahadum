<?php

namespace App\Services;

use App\Models\User;

class ConsumerAccess
{
    public const ABILITIES = ['referrals.view', 'payouts.view', 'payouts.request', 'billing.subscriptions.manage', 'billing.databundles.manage'];

    /** Adult direct learners may manage their own purchases and referral earnings. */
    public static function allows(User $user, string $ability): bool
    {
        if (! in_array($ability, self::ABILITIES, true) || $user->status !== 'active'
            || ! $user->hasRole('student') || $user->date_of_birth === null || $user->date_of_birth->age < 18) {
            return false;
        }

        $learner = $user->learnerProfile;

        return $learner !== null && $learner->family_id === null && $learner->organization_id === null
            && $user->organization_id === null && ! $user->organizations()->exists();
    }
}
