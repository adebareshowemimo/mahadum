<?php

namespace App\Services\Gamification;

use App\Models\LearnerProfile;
use App\Models\Streak;
use App\Models\StreakProtection;
use Illuminate\Support\Carbon;

/**
 * Streak engine. Called when a learner does qualifying activity (e.g. completes
 * a lesson). Same-day activity is idempotent; a one-day gap extends the streak;
 * a larger gap resets it unless an active protection (telco grace / shield)
 * covers the miss — framed as "protected", never punitive (Rule per BRD).
 */
class StreakService
{
    public function recordActivity(LearnerProfile $learner): Streak
    {
        $streak = Streak::firstOrCreate(
            ['learner_profile_id' => $learner->id],
            ['current_count' => 0, 'longest_count' => 0, 'state' => 'active'],
        );

        $today = Carbon::today();
        $last = $streak->last_active_date ? Carbon::parse($streak->last_active_date) : null;

        if ($last && $last->isSameDay($today)) {
            return $streak; // already counted today
        }

        if (! $last) {
            $streak->current_count = 1;
        } elseif ($last->copy()->addDay()->isSameDay($today)) {
            $streak->current_count++;
        } elseif ($this->consumeProtection($learner)) {
            $streak->current_count++; // miss covered by grace/shield
        } else {
            $streak->current_count = 1; // streak reset
        }

        $streak->longest_count = max($streak->longest_count, $streak->current_count);
        $streak->last_active_date = $today;
        $streak->state = 'active';
        $streak->frozen_until = null;
        $streak->save();

        return $streak;
    }

    /** Arm a streak shield (payment/coins handled by the wallet slice). */
    public function armShield(LearnerProfile $learner, string $source = 'coin_purchase', int $days = 14): StreakProtection
    {
        $protection = StreakProtection::create([
            'learner_profile_id' => $learner->id,
            'type' => 'shield',
            'source' => $source,
            'active_from' => now(),
            'active_to' => now()->addDays($days),
            'consumed' => false,
        ]);

        $learner->streak()->updateOrCreate([], ['frozen_until' => $protection->active_to]);

        return $protection;
    }

    private function consumeProtection(LearnerProfile $learner): bool
    {
        $protection = StreakProtection::where('learner_profile_id', $learner->id)
            ->where('consumed', false)
            ->where('active_to', '>=', now())
            ->orderBy('active_to')
            ->first();

        if (! $protection) {
            return false;
        }

        $protection->update(['consumed' => true]);

        return true;
    }
}
