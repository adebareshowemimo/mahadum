<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Organization;
use App\Models\User;

trait ResolvesOrganization
{
    /**
     * Ensure the caller may act within an organization: super_admin (global) or
     * an active member. The route permission (can:schools.*) grants the
     * capability; this grants the scope.
     */
    protected function authorizeOrg(User $user, Organization $organization): void
    {
        if ($user->hasRole('super_admin')) {
            return;
        }

        $isMember = $user->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivot('status', 'active')
            ->exists();

        abort_unless($isMember, 403, 'You do not belong to this organization.');
    }
}
