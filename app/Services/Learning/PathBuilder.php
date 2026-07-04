<?php

namespace App\Services\Learning;

use App\Models\Enrollment;
use Illuminate\Support\Collection;

/**
 * Generates a learner's path from a course's PUBLISHED lessons, ordered by
 * level then lesson position. The first node is active; the rest are locked
 * until the preceding lesson is completed (LessonCompletionController unlocks).
 */
class PathBuilder
{
    public function build(Enrollment $enrollment): Collection
    {
        $lessons = $enrollment->course->levels()
            ->orderBy('position')
            ->with(['lessons' => fn ($q) => $q->whereNotNull('published_at')->orderBy('position')])
            ->get()
            ->flatMap->lessons;

        $position = 0;

        return $lessons->map(function ($lesson) use ($enrollment, &$position) {
            $position++;

            return $enrollment->pathNodes()->firstOrCreate(
                ['lesson_id' => $lesson->id],
                ['state' => $position === 1 ? 'active' : 'locked', 'position' => $position],
            );
        });
    }
}
