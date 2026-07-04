<?php

namespace App\Http\Resources;

use App\Models\Family;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Family
 */
class FamilyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'child_limit' => $this->child_limit,
            'learners' => LearnerProfileResource::collection($this->whenLoaded('learnerProfiles')),
        ];
    }
}
