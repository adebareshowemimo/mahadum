<?php

namespace App\Policies;

use App\Models\OrganizationUser;
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
        return $user->can('schools.classes.view') && $this->hasTenant();
    }

    public function view(User $user, SchoolClass $class): bool
    {
        if ($class->teacher_user_id === $user->id && $this->sameTenant($class) && $this->activeTeacher($user, $class)) {
            return true; // own classroom
        }

        return $user->can('schools.classes.view') && $this->sameTenant($class);
    }

    public function create(User $user): bool
    {
        return $user->can('schools.classes.manage') && $this->hasTenant();
    }

    public function update(User $user, SchoolClass $class): bool
    {
        if (! $user->can('schools.classes.manage') || ! $this->sameTenant($class)) {
            return false;
        }

        // Teachers operate their own classrooms. School admins retain
        // organization-wide class management.
        if ($user->hasRole('teacher') && ! $user->hasRole('school_admin')) {
            return (int) $class->teacher_user_id === (int) $user->id;
        }

        return true;
    }

    public function delete(User $user, SchoolClass $class): bool
    {
        return $this->update($user, $class);
    }

    public function createAssignment(User $user, SchoolClass $class): bool
    {
        return $user->can('schools.assignments.create') && $this->sameTenant($class)
            && ($user->hasRole('school_admin') || ((int) $class->teacher_user_id === (int) $user->id && $this->activeTeacher($user, $class)));
    }

    public function gradeAssignment(User $user, SchoolClass $class): bool
    {
        return $user->can('schools.assignments.review') && $this->sameTenant($class)
            && (int) $class->teacher_user_id === (int) $user->id && $this->activeTeacher($user, $class);
    }

    private function activeTeacher(User $user, SchoolClass $class): bool
    {
        return OrganizationUser::where('organization_id', $class->organization_id)
            ->where('user_id', $user->id)->where('role', 'teacher')->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))->exists();
    }

    private function sameTenant(SchoolClass $class): bool
    {
        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;

        return $tenantId !== null && (int) $class->organization_id === (int) $tenantId;
    }

    private function hasTenant(): bool
    {
        return app()->bound('currentTenantId') && app('currentTenantId') !== null;
    }
}
