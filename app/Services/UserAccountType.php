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
        $chosen = match ($user->signup_account_type) {
            'family', 'parent' => 'family',
            'individual', 'learner' => 'single',
            'teacher' => 'teacher',
            'school', 'educator_school' => 'school',
            'institution' => 'institution',
            default => null,
        };
        if ($chosen !== null) {
            return $chosen;
        }
        if ($user->hasRole('teacher') && ! $user->hasRole('school_admin')) {
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
            return $query->where(fn ($sub) => $sub->whereHas('ownedFamilies')
                ->orWhereIn('signup_account_type', ['family', 'parent']));
        }
        $query->whereDoesntHave('ownedFamilies')->where(fn ($sub) => $sub->whereNull('signup_account_type')
            ->orWhereNotIn('signup_account_type', ['family', 'parent']));
        $teacher = fn ($sub) => $sub->where('signup_account_type', 'teacher')
            ->orWhere(fn ($legacy) => $legacy->whereNull('signup_account_type')
                ->whereHas('roles', fn ($role) => $role->where('name', 'teacher'))
                ->whereDoesntHave('roles', fn ($role) => $role->where('name', 'school_admin')));
        $notLegacyTeacher = fn ($sub) => $sub->whereDoesntHave('roles', fn ($role) => $role->where('name', 'teacher'))
            ->orWhereHas('roles', fn ($role) => $role->where('name', 'school_admin'));
        if ($type === 'teacher') {
            return $query->where($teacher);
        }
        if ($type === 'institution') {
            return $query->where(fn ($sub) => $sub->where('signup_account_type', 'institution')
                ->orWhere(fn ($legacy) => $legacy->whereNull('signup_account_type')
                    ->whereHas('organizations', fn ($org) => $org->where('organizations.type', 'institution'))
                    ->where($notLegacyTeacher)));
        }
        if ($type === 'school') {
            return $query->where(fn ($sub) => $sub->whereIn('signup_account_type', ['school', 'educator_school'])
                ->orWhere(fn ($legacy) => $legacy->whereNull('signup_account_type')
                    ->whereDoesntHave('organizations', fn ($org) => $org->where('organizations.type', 'institution'))
                    ->whereHas('organizations')->where($notLegacyTeacher)));
        }

        return $query->where(fn ($sub) => $sub->whereIn('signup_account_type', ['individual', 'learner'])
            ->orWhere(fn ($legacy) => $legacy->whereNull('signup_account_type')
                ->whereDoesntHave('organizations')->where($notLegacyTeacher)));
    }
}
