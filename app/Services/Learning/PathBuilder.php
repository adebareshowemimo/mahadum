<?php

namespace App\Services\Learning;

use App\Models\Enrollment;
use App\Models\PlacementAssessment;
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
        $placement = PlacementAssessment::where('learner_profile_id', $enrollment->learner_profile_id)
            ->where('language_id', $enrollment->course->language_id)->latest('id')->first();
        $intermediateStart = $enrollment->course->levels()->where('position', '<=', 2)
            ->whereHas('lessons', fn ($query) => $query->whereNotNull('published_at'))->max('position');
        $start = $enrollment->learnerProfile->starting_level_position
            ?? ($placement?->result_level === 'Intermediate' ? (int) ($intermediateStart ?? 0) : 0);
        $lessons = $enrollment->course->levels()
            ->where('position', '>=', $start)
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
