<?php

namespace App\Services\Gamification;

use App\Models\Badge;
use App\Models\LearnerBadge;
use App\Models\LearnerProfile;
use App\Models\QuizAttempt;

/**
 * Evaluates badge conditions for a learner and awards any newly-earned badges.
 * Idempotent — a badge is only ever awarded once. Returns the newly granted
 * badge codes/names for inclusion in the lesson-completion response.
 */
class BadgeService
{
    /**
     * @return array<int, array{code:string, name:string}>
     */
    public function evaluate(LearnerProfile $learner): array
    {
        $earnedCodes = LearnerBadge::where('learner_profile_id', $learner->id)
            ->join('badges', 'badges.id', '=', 'learner_badges.badge_id')
            ->pluck('badges.code')->all();

        $newlyEarned = [];

        foreach ($this->conditions($learner) as $code => $met) {
            if ($met && ! in_array($code, $earnedCodes, true)) {
                if ($badge = Badge::where('code', $code)->first()) {
                    LearnerBadge::create([
                        'learner_profile_id' => $learner->id,
                        'badge_id' => $badge->id,
                        'earned_at' => now(),
                    ]);
                    $newlyEarned[] = ['code' => $badge->code, 'name' => $badge->name];
                }
            }
        }

        return $newlyEarned;
    }

    /**
     * @return array<string, bool> badge code => condition met
     */
    private function conditions(LearnerProfile $learner): array
    {
        $completedLessons = $learner->lessonProgress()->where('status', 'completed')->count();
        $streakCount = (int) optional($learner->streak)->current_count;
        $perfectQuiz = QuizAttempt::where('learner_profile_id', $learner->id)
            ->where('score', '>=', 1.0)->exists();

        return [
            'first_lesson' => $completedLessons >= 1,
            'streak_7' => $streakCount >= 7,
            'sharp_shooter' => $perfectQuiz,
        ];
    }
}
