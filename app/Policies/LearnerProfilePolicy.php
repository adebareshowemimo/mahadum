<?php

namespace App\Policies;

use App\Models\LearnerProfile;
use App\Models\User;

/**
 * A learner profile is reachable by:
 *   • the learner themselves (adult learner whose user_id matches) — self-access,
 *   • the parent who owns the family,
 *   • org staff (teacher/supervisor/school_admin) with learning.progress.view,
 *     limited to profiles in the active tenant.
 * This is why `student` needs no global permissions — self-access lives here.
 */
class LearnerProfilePolicy
{
    public function view(User $user, LearnerProfile $profile): bool
    {
        return $this->isSelf($user, $profile)
            || $this->isParentOwner($user, $profile)
            || ($user->can('learning.progress.view') && $this->sameTenant($profile));
    }

    public function update(User $user, LearnerProfile $profile): bool
    {
        // The learner edits their own profile; a parent manages their children.
        return $this->isSelf($user, $profile)
            || ($user->can('family.manage') && $this->isParentOwner($user, $profile));
    }

    public function reviewSubmissions(User $user, LearnerProfile $profile): bool
    {
        return $user->can('learning.submissions.review')
            && ($this->isParentOwner($user, $profile) || $this->sameTenant($profile));
    }

    /**
     * Self or parent only — deliberately excludes the same-tenant-staff branch
     * that `view()` grants. For actions that *redeem a reward* on the
     * learner's behalf (ads, hearts refills) rather than just reading their
     * progress: a teacher/supervisor/school_admin having `learning.progress.view`
     * should never be able to trigger or claim a reward for a learner they
     * aren't the learner or parent of.
     */
    public function redeemReward(User $user, LearnerProfile $profile): bool
    {
        return $this->isSelf($user, $profile) || $this->isParentOwner($user, $profile);
    }

    private function isSelf(User $user, LearnerProfile $profile): bool
    {
        return $profile->user_id !== null && (int) $profile->user_id === (int) $user->id;
    }

    private function isParentOwner(User $user, LearnerProfile $profile): bool
    {
        return $profile->family
            && (int) $profile->family->owner_user_id === (int) $user->id;
    }

    private function sameTenant(LearnerProfile $profile): bool
    {
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        return $tenantId !== null && (int) $profile->organization_id === (int) $tenantId;
    }
}
