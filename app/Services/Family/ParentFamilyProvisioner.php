<?php

namespace App\Services\Family;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParentFamilyProvisioner
{
    /** Provision an admin-granted parent just as Family registration does. */
    public function ensureFor(User $user): Family
    {
        return DB::transaction(function () use ($user): Family {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $family = $user->ownedFamilies()->first();
            if ($family !== null) {
                return $family;
            }

            // An intentional deletion must not silently create a replacement household.
            if ($user->ownedFamilies()->onlyTrashed()->exists()) {
                throw ValidationException::withMessages([
                    'role' => 'This account has a deleted family. Review that family before granting Parent access.',
                ]);
            }

            $family = Family::create([
                'owner_user_id' => $user->id,
                'name' => $user->first_name."'s Family",
            ]);
            FamilyMember::create([
                'family_id' => $family->id,
                'user_id' => $user->id,
                'relationship' => 'parent',
                'is_account_owner' => true,
            ]);

            return $family;
        });
    }
}
