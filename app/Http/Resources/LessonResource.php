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
            'is_free' => $this->course_level_id === null || (bool) $this->courseLevel->is_free,
            'is_free_preview' => $this->course_level_id === null || (bool) $this->courseLevel->is_free,
            'language_id' => $this->language_id,
            'standalone_level' => $this->standalone_level,
            'id' => $this->id,
            'course_level_id' => $this->course_level_id,
            'title' => $this->title,
            'summary' => $this->summary,
            'kind' => $this->kind,
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
