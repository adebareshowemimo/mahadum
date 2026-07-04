<?php

namespace App\Http\Resources;

use App\Models\LearnerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LearnerProfile
 */
class LearnerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'avatar_id' => $this->avatar_id,
            'age_band' => $this->age_band,
            'current_level' => $this->current_level,
            'target_language' => $this->whenLoaded('targetLanguage', fn () => $this->targetLanguage?->code),
            'is_child' => $this->user_id === null,
            'pin_protected' => (bool) $this->parental_pin_protected,
        ];
    }
}
