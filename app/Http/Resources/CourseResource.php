<?php

namespace App\Http\Resources;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Course
 */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'level_band' => $this->level_band,
            'status' => $this->status,
            'is_published' => (bool) $this->is_published,
            'language' => $this->whenLoaded('language', fn () => $this->language->code),
            'owner' => $this->whenLoaded('ownerUser', fn () => $this->ownerUser?->name),
            'levels_count' => $this->whenCounted('levels'),
            'levels' => CourseLevelResource::collection($this->whenLoaded('levels')),
        ];
    }
}
