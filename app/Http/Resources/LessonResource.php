<?php

namespace App\Http\Resources;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lesson
 */
class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'position' => $this->position,
            'est_minutes' => $this->est_minutes,
            'is_locked_by_default' => (bool) $this->is_locked_by_default,
            'version' => $this->version,
            'published_at' => $this->published_at,
            'is_published' => $this->published_at !== null,
            'components' => LessonComponentResource::collection($this->whenLoaded('components')),
        ];
    }
}
