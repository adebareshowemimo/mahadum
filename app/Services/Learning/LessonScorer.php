<?php

namespace App\Services\Learning;

use App\Models\Lesson;
use Illuminate\Support\Collection;

/**
 * Lesson completion gating + weighted score (Business Rule 3):
 * 30% video + 20% quiz + 25% speaking + 15% assignment + 10% engagement.
 *
 * Weights are normalised over the dimensions actually present in the lesson
 * (engagement always counts), so a lesson without an assignment can still
 * reach 1.0.
 */
class LessonScorer
{
    private const WEIGHTS = [
        'video' => 0.30,
        'quiz' => 0.20,
        'speaking' => 0.25,
        'assignment' => 0.15,
    ];

    private const ENGAGEMENT_WEIGHT = 0.10;

    /**
     * Required components that are not yet complete.
     *
     * @param  Collection  $progressByComponent  keyed by lesson_component_id
     * @return array<int, int> incomplete component ids
     */
    public function incompleteRequired(Lesson $lesson, Collection $progressByComponent): array
    {
        return $lesson->components
            ->where('is_required', true)
            ->filter(fn ($c) => optional($progressByComponent->get($c->id))->status !== 'complete')
            ->pluck('id')->values()->all();
    }

    /**
     * @param  Collection  $progressByComponent  keyed by lesson_component_id
     */
    public function score(Lesson $lesson, Collection $progressByComponent): float
    {
        $ratio = fn ($c) => optional($progressByComponent->get($c->id))->status === 'complete'
            ? (float) (optional($progressByComponent->get($c->id))->score ?? 1.0)
            : 0.0;

        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach (self::WEIGHTS as $type => $weight) {
            $components = $lesson->components->where('type', $type);
            if ($components->isEmpty()) {
                continue;
            }
            $dimension = $components->avg($ratio);
            $weightedSum += $weight * $dimension;
            $totalWeight += $weight;
        }

        // Engagement: reaching completion earns the full engagement slice.
        // (This always adds weight, so $totalWeight is never zero.)
        $weightedSum += self::ENGAGEMENT_WEIGHT * 1.0;
        $totalWeight += self::ENGAGEMENT_WEIGHT;

        return round($weightedSum / $totalWeight, 4);
    }
}
