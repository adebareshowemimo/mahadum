<?php

namespace App\Services\Billing;

use App\Models\LearnerProfile;
use App\Models\Plan;
use App\Models\User;
use App\Services\UserAccountType;

class SubscriptionEligibility
{
    public function problem(User $user, Plan $plan): ?string
    {
        if ($plan->audience === 'family' && UserAccountType::forUser($user) !== 'family') {
            return 'Family plans are available only to Family accounts.';
        }

        if ($plan->max_profiles !== null && $this->profileCount($user) > (int) $plan->max_profiles) {
            return "This plan supports {$plan->max_profiles} learner profile(s). Choose a plan that covers all your learners.";
        }

        return null;
    }

    public function profileCount(User $user): int
    {
        return LearnerProfile::query()->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('family', fn ($family) => $family->where('owner_user_id', $user->id));
        })->count();
    }

    public function coversLearner(User $user, Plan $plan, LearnerProfile $learner): bool
    {
        if ($this->problem($user, $plan) === null) {
            return true;
        }

        // Existing over-limit subscriptions cannot grant every child paid access.
        // Stable ordering gives the purchased seats to the oldest profiles.
        if ($plan->max_profiles === null || ($plan->audience === 'family' && UserAccountType::forUser($user) !== 'family')) {
            return false;
        }

        return LearnerProfile::query()->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('family', fn ($family) => $family->where('owner_user_id', $user->id));
        })->where('id', '<=', $learner->id)->count() <= (int) $plan->max_profiles;
    }
}
