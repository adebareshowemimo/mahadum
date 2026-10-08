<?php

namespace App\Services\Gamification;

use App\Models\Badge;
use App\Models\CourseLevel;
use App\Models\LearnerBadge;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Notifications\FamilyActivityAlert;
use App\Notifications\LearningLevelUp;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates badge conditions for a learner and awards any newly-earned badges.
 * Idempotent — a badge is only ever awarded once. Returns the newly granted
 * badge codes/names for inclusion in the lesson-completion response.
 *
 * Learning-tier badges (`tier_0`…`tier_5`) are awarded here too: reaching a
 * level grants that tier's badge and sends the learner (or their guardian) a
 * congratulations notification — feedback Sept 4, §4.
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

                    DB::afterCommit(function () use ($learner, $badge) {
                        if (str_starts_with($badge->code, 'tier_')) {
                            $this->notifyLevelUp($learner, (int) substr($badge->code, 5), $badge->name);
                        } else {
                            rescue(fn () => ($learner->family_id ? $learner->family->owner : $learner->user)?->notify(new FamilyActivityAlert('achievement', 'A learning achievement is ready', 'A new badge has been earned. Open achievements to celebrate the progress.', '/achievements')), report: true);
                        }
                    });
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

        $conditions = [
            'first_lesson' => $completedLessons >= 1,
            'streak_7' => $streakCount >= 7,
            'sharp_shooter' => $perfectQuiz,
        ];

        $completedIds = $learner->lessonProgress()->where('status', 'completed')->pluck('lesson_id');
        $conditions['tier_0'] = Lesson::whereIn('id', $completedIds)->whereHas('courseLevel', fn ($q) => $q->where('position', 0))->exists();
        foreach (range(1, 5) as $level) {
            $conditions["tier_{$level}"] = CourseLevel::where('position', $level)
                ->whereHas('lessons', fn ($q) => $q->whereNotNull('published_at'))
                ->whereDoesntHave('lessons', fn ($q) => $q->whereNotNull('published_at')
                    ->whereNotIn('id', $completedIds))
                ->exists();
        }

        return $conditions;
    }

    private function notifyLevelUp(LearnerProfile $learner, int $level, string $badgeName): void
    {
        $notifiable = $learner->user ?? $learner->family?->owner;

        rescue(fn () => $notifiable?->notify(new LearningLevelUp($learner->display_name, $level, $badgeName)), report: true);
    }
}
