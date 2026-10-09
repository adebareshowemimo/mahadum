<?php

namespace App\Services\Ads;

use App\Models\LearnerProfile;
use App\Models\User;
use App\Services\Billing\EntitlementResolver;

class AdAudience
{
    public function __construct(private EntitlementResolver $entitlements) {}

    public function allowed(User $actor, LearnerProfile $learner): bool
    {
        return ! $actor->hasAnyRole(['super_admin', 'content_owner', 'teacher', 'school_admin', 'supervisor'])
            && (bool) $this->entitlements->forLearner($learner)['ads'];
    }
}
