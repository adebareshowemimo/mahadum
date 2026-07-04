<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'locale' => $this->locale,
            'roles' => $this->getRoleNames(),
            'active_organization_id' => app()->bound('currentTenantId') ? app('currentTenantId') : null,
        ];
    }
}
