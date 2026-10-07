<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Mutually exclusive directory classifications; never a permission grant. */
class UserAccountType
{
    /** @param Builder<User> $query
     * @return Builder<User>
     */
    public static function filter(Builder $query, string $type): Builder
    {
        $institution = fn ($org) => $org->where('organizations.type', 'institution');
        if ($type === 'institution') {
            return $query->whereHas('organizations', $institution);
        }
        if ($type === 'school') {
            return $query->whereDoesntHave('organizations', $institution)->whereHas('organizations');
        }
        $query->whereDoesntHave('organizations');
        if ($type === 'teacher') {
            return $query->where('signup_account_type', 'teacher');
        }
        $query->where(fn ($sub) => $sub->whereNull('signup_account_type')->orWhere('signup_account_type', '!=', 'teacher'));

        return $type === 'family' ? $query->whereHas('ownedFamilies') : $query->whereDoesntHave('ownedFamilies');
    }
}
