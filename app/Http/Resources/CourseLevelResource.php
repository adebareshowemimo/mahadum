<?php

namespace App\Http\Resources;

use App\Models\CourseLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CourseLevel
 */
class CourseLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'position' => $this->position,
            'has_assessment' => (bool) $this->has_assessment,
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
        ];
    }
}
