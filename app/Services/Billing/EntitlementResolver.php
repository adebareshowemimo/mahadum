<?php

namespace App\Services\Billing;

use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

class EntitlementResolver
{
    /** @return array<string, bool|int|string> */
    public function forUser(User $user): array
    {
        return $this->fromPlan($this->activePlan(User::class, $user->id));
    }

    /** @return array<string, bool|int|string> */
    public function forLearner(LearnerProfile $learner): array
    {
        if ($learner->user_id !== null) {
            $plan = $this->activePlan(User::class, $learner->user_id);
            if ($plan !== null) {
                return $this->fromPlan($plan);
            }
        }

        $learner->loadMissing('family');
        if ($learner->family?->owner_user_id !== null) {
            $plan = $this->activePlan(User::class, $learner->family->owner_user_id);
            if ($plan !== null) {
                return $this->fromPlan($plan);
            }
        }

        if ($learner->organization_id !== null) {
            $plan = $this->activePlan(Organization::class, $learner->organization_id);
            if ($plan !== null) {
                return $this->fromPlan($plan);
            }
        }

        return $this->fromPlan(null);
    }

    public function activePlan(string $subscriberType, int $subscriberId): ?Plan
    {
        return Subscription::query()
            ->with('plan')
            ->where('subscriber_type', $subscriberType)
            ->where('subscriber_id', $subscriberId)
            ->whereIn('status', ['active', 'grace'])
            ->latest()
            ->first()
            ?->plan;
    }

    /** @return array<string, bool|int|string> */
    public function fromPlan(?Plan $plan): array
    {
        if ($plan === null) {
            return [
                'tier' => 'free',
                'tier_name' => 'Free',
                'ads' => true,
                'offline_download' => false,
                'unlimited_hearts' => false,
                'family_dashboard' => false,
                'teacher_analytics' => false,
                'max_profiles' => 1,
            ];
        }

        $features = $plan->features ?? [];

        return [
            'tier' => $plan->code,
            'tier_name' => $plan->name,
            'ads' => (bool) ($features['ads'] ?? false),
            'offline_download' => (bool) ($features['offline_download'] ?? false),
            'unlimited_hearts' => (bool) ($features['unlimited_hearts'] ?? false),
            'family_dashboard' => (bool) ($features['family_dashboard'] ?? false),
            'teacher_analytics' => (bool) ($features['teacher_analytics'] ?? false),
            'max_profiles' => $plan->max_profiles ?? 1,
        ];
    }
}
