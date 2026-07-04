<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

/**
 * Org-scoped. Permission gives the *capability*; the org match gives the *scope*.
 * The active tenant is resolved by IdentifyTenant into app('currentTenantId').
 * Teachers can always see their own class even without the broader view grant.
 */
class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('schools.classes.view');
    }

    public function view(User $user, SchoolClass $class): bool
    {
        if ($class->teacher_user_id === $user->id) {
            return true; // own classroom
        }

        return $user->can('schools.classes.view') && $this->sameTenant($class);
    }

    public function create(User $user): bool
    {
        return $user->can('schools.classes.manage');
    }

    public function update(User $user, SchoolClass $class): bool
    {
        return $user->can('schools.classes.manage') && $this->sameTenant($class);
    }

    public function delete(User $user, SchoolClass $class): bool
    {
        return $user->can('schools.classes.manage') && $this->sameTenant($class);
    }

    private function sameTenant(SchoolClass $class): bool
    {
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        return $tenantId !== null && (int) $class->organization_id === (int) $tenantId;
    }
}
