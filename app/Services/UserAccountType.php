<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Mutually exclusive directory classifications; never a permission grant. */
class UserAccountType
{
    public static function forUser(User $user): string
    {
        if (($user->owned_families_count ?? $user->ownedFamilies()->count()) > 0) {
            return 'family';
        }
        if (($user->signup_account_type === 'teacher' || $user->hasRole('teacher')) && ! $user->hasRole('school_admin')) {
            return 'teacher';
        }
        $organizations = $user->organizations;

        return $organizations->contains('type', 'institution') ? 'institution' : ($organizations->isNotEmpty() ? 'school' : 'single');
    }

    /** @param Builder<User> $query
     * @return Builder<User>
     */
    public static function filter(Builder $query, string $type): Builder
    {
        if ($type === 'family') {
            return $query->whereHas('ownedFamilies');
        }
        $query->whereDoesntHave('ownedFamilies');
        $teacher = fn ($sub) => $sub->where('signup_account_type', 'teacher')->orWhereHas('roles', fn ($role) => $role->where('name', 'teacher'));
        if ($type === 'teacher') {
            return $query->where($teacher)->whereDoesntHave('roles', fn ($role) => $role->where('name', 'school_admin'));
        }
        $query->where(fn ($sub) => $sub->whereHas('roles', fn ($role) => $role->where('name', 'school_admin'))
            ->orWhere(fn ($other) => $other->where(fn ($intent) => $intent->whereNull('signup_account_type')->orWhere('signup_account_type', '!=', 'teacher'))
                ->whereDoesntHave('roles', fn ($role) => $role->where('name', 'teacher'))));
        $institution = fn ($org) => $org->where('organizations.type', 'institution');
        if ($type === 'institution') {
            return $query->whereHas('organizations', $institution);
        }
        if ($type === 'school') {
            return $query->whereDoesntHave('organizations', $institution)->whereHas('organizations');
        }

        return $query->whereDoesntHave('organizations');
    }
}
