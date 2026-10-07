<?php

namespace App\Services\Family;

use App\Models\AssignmentSubmission;
use App\Models\Chore;
use App\Models\ClassAssignmentSubmission;
use App\Models\Family;
use App\Models\FamilyAlertPreference;
use App\Models\LearnerProfile;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\SpeakingSubmission;
use App\Notifications\FamilyActivityAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Parent-selected thresholds, episode deduplication and guardian-only delivery. */
class FamilyAlertService
{
    public function evaluate(Family $family): void
    {
        $prefs = FamilyAlertPreference::where('family_id', $family->id)->first();
        $owner = $family->owner;
        if (! $prefs || $owner->status !== 'active' || ! $owner->hasRole('parent') || ! $owner->hasVerifiedEmail()) {
            return;
        }
        if ($prefs->low_balance_coins !== null) {
            $balance = app(WalletService::class)->walletFor($family)->coin_balance;
            $episode = $balance <= $prefs->low_balance_coins ? 'threshold:'.$prefs->low_balance_coins : null;
            $this->notifyEpisode($family, 'low_balance', $episode, new FamilyActivityAlert('low_balance', 'Family coins are running low', 'Your family wallet is at or below the coin threshold you selected.', '/wallet'));
        } else {
            $this->reset($family, 'low_balance');
        }
        foreach ($family->learnerProfiles as $learner) {
            $last = collect([
                LessonProgress::where('learner_profile_id', $learner->id)->max('updated_at'),
                QuizAttempt::where('learner_profile_id', $learner->id)->max('updated_at'),
                SpeakingSubmission::where('learner_profile_id', $learner->id)->max('created_at'),
                $learner->created_at?->toDateTimeString(),
            ])->filter()->max();
            $episode = $prefs->inactive_days !== null && $last && Carbon::parse($last)->lte(now()->subDays($prefs->inactive_days)) ? $last.':'.$prefs->inactive_days : null;
            $this->notifyEpisode($family, 'inactive:'.$learner->id, $episode, new FamilyActivityAlert('learning_inactivity', 'Time for a family learning check-in', 'A learner has reached the inactivity period you selected. A short lesson together can help them return.', '/family'));
        }
        $pending = [];
        if ($prefs->review_alerts) {
            $ids = $family->learnerProfiles->pluck('id');
            $groups = [
                'chore' => Chore::where('family_id', $family->id)->where('status', 'pending_review')->whereHas('submissions', fn ($query) => $query->whereNotNull('submitted_at')->whereNull('decision'))->get(),
                'assignment' => AssignmentSubmission::whereIn('learner_profile_id', $ids)->where('parent_review_status', 'pending')->get(),
                'class_assignment' => ClassAssignmentSubmission::whereIn('learner_profile_id', $ids)->where('parent_review_status', 'pending')->get(),
                'speaking' => SpeakingSubmission::whereIn('learner_profile_id', $ids)->where('status', 'needs_review')->get(),
            ];
            foreach ($groups as $kind => $rows) {
                foreach ($rows as $row) {
                    $key = 'review:'.$kind.':'.$row->id;
                    $pending[] = $key;
                    $this->notifyEpisode($family, $key, $row->updated_at->toDateTimeString(), new FamilyActivityAlert('review_needed', 'Family work is ready for review', 'Open your review queue to check the submitted work. Coin rewards still wait for your approval.', '/reviews'));
                }
            }
        }
        DB::table('family_alert_states')->where('family_id', $family->id)->where('key', 'like', 'review:%')->whereNotIn('key', $pending)->update(['episode' => null, 'updated_at' => now()]);
    }

    public function forLearner(LearnerProfile $learner): void
    {
        if ($learner->family) {
            $this->evaluate($learner->family);
        }
    }

    private function reset(Family $family, string $key): void
    {
        DB::table('family_alert_states')->where('family_id', $family->id)->where('key', $key)->update(['episode' => null, 'updated_at' => now()]);
    }

    private function notifyEpisode(Family $family, string $key, ?string $episode, FamilyActivityAlert $notification): void
    {
        $new = DB::transaction(function () use ($family, $key, $episode) {
            DB::table('family_alert_states')->insertOrIgnore(['family_id' => $family->id, 'key' => $key, 'episode' => null, 'created_at' => now(), 'updated_at' => now()]);
            $state = DB::table('family_alert_states')->where('family_id', $family->id)->where('key', $key)->lockForUpdate()->first();
            if ($state->episode === $episode) {
                return false;
            }
            DB::table('family_alert_states')->where('id', $state->id)->update(['episode' => $episode, 'updated_at' => now()]);

            return $episode !== null;
        });
        if ($new) {
            try {
                $family->owner->notify($notification);
            } catch (\Throwable $error) {
                DB::table('family_alert_states')->where('family_id', $family->id)->where('key', $key)->where('episode', $episode)
                    ->update(['episode' => null, 'updated_at' => now()]);
                throw $error;
            }
        }
    }
}
