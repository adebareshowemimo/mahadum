<?php

namespace App\Services\Gamification;

use App\Models\Heart;
use App\Models\LearnerProfile;
use Illuminate\Support\Facades\DB;

class PracticeModeService
{
    public const MAX_HEARTS = 5;

    public const PAUSE_HOURS = 12;

    /** @return array{current:int, practice_mode:bool, competitive_paused_until:?string} */
    public function state(LearnerProfile $learner): array
    {
        $heart = Heart::firstOrCreate(
            ['learner_profile_id' => $learner->id],
            ['current' => self::MAX_HEARTS],
        );

        if ($heart->competitive_paused_until?->isPast()) {
            $heart->update([
                'current' => self::MAX_HEARTS,
                'refills_at' => null,
                'competitive_paused_until' => null,
            ]);
            $heart->refresh();
        }

        return [
            'current' => (int) $heart->current,
            'practice_mode' => $heart->current <= 0 && $heart->competitive_paused_until?->isFuture() === true,
            'competitive_paused_until' => $heart->competitive_paused_until?->toISOString(),
        ];
    }

    /**
     * Apply a quiz mistake and return the resulting competitive state. Learning
     * itself is never blocked: zero hearts only pauses XP and leaderboards.
     *
     * @return array{current:int, practice_mode:bool, competitive_paused_until:?string}
     */
    public function applyMistake(LearnerProfile $learner, bool $loseHeart): array
    {
        return DB::transaction(function () use ($learner, $loseHeart): array {
            Heart::firstOrCreate(
                ['learner_profile_id' => $learner->id],
                ['current' => self::MAX_HEARTS],
            );

            $heart = Heart::where('learner_profile_id', $learner->id)->lockForUpdate()->firstOrFail();

            if ($heart->competitive_paused_until?->isPast()) {
                $heart->fill([
                    'current' => self::MAX_HEARTS,
                    'refills_at' => null,
                    'competitive_paused_until' => null,
                ]);
            }

            if ($loseHeart && $heart->current > 0) {
                $heart->current--;
                if ($heart->current === 0) {
                    $resumeAt = now()->addHours(self::PAUSE_HOURS);
                    $heart->refills_at = $resumeAt;
                    $heart->competitive_paused_until = $resumeAt;
                }
            }

            if ($heart->isDirty()) {
                $heart->save();
            }

            return [
                'current' => (int) $heart->current,
                'practice_mode' => $heart->current <= 0 && $heart->competitive_paused_until?->isFuture() === true,
                'competitive_paused_until' => $heart->competitive_paused_until?->toISOString(),
            ];
        });
    }
}
