<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Family;
use App\Models\User;

trait ResolvesFamily
{
    /**
     * The caller's family (the one they own). Parents own exactly one family in
     * the MVP; if multiple are ever supported, accept a family_id and authorize it.
     */
    protected function family(User $user): Family
    {
        return $user->ownedFamilies()->firstOrFail();
    }
}
