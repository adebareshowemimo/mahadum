<?php

namespace App\Http\Resources;

use App\Models\LearnerProfile;
use App\Services\Family\WalletService;
use App\Services\Gamification\LearningLevelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin LearnerProfile
 */
class LearnerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $learningLevel = app(LearningLevelService::class)->forLearner($this->resource);

        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'avatar_id' => $this->avatar_id,
            'avatar_url' => $this->profilePhoto
                ? (str_starts_with($this->profilePhoto->url, 'http')
                    ? $this->profilePhoto->url
                    : Storage::disk('public')->url($this->profilePhoto->url))
                : null,
            'age_band' => $this->age_band,
            'current_level' => $this->current_level,
            'learning_level' => $learningLevel,
            'target_language' => $this->whenLoaded('targetLanguage', fn () => $this->targetLanguage?->code),
            'is_child' => $this->user_id === null,
            'pin_protected' => $this->parental_pin !== null,
            'coin_balance' => app(WalletService::class)->walletFor($this->resource)->coin_balance,
        ];
    }
}
