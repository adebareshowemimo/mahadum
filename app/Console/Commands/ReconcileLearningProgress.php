<?php

namespace App\Console\Commands;

use App\Models\Badge;
use App\Models\LearnerBadge;
use App\Models\LearnerPathNode;
use App\Models\LessonProgress;
use App\Models\Streak;
use App\Services\Learning\LessonScorer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReconcileLearningProgress extends Command
{
    protected $signature = 'learning:reconcile-progress {--apply : Apply the reported repairs}';

    protected $description = 'Audit and repair finished lessons, First Steps badges, and missing first streaks';

    public function handle(LessonScorer $scorer): int
    {
        $apply = (bool) $this->option('apply');
        $lessonRepairs = 0;
        $badgeRepairs = 0;
        $streakRepairs = 0;
        $monthlyRepairs = 0;

        LessonProgress::where('status', '!=', 'completed')->with(['lesson.components', 'componentProgress'])
            ->orderBy('id')->chunkById(100, function ($rows) use ($scorer, $apply, &$lessonRepairs) {
                foreach ($rows as $progress) {
                    $lesson = $progress->lesson;
                    if (! $lesson || $lesson->components->where('is_required', true)->isEmpty()) {
                        continue;
                    }
                    $components = $progress->componentProgress->keyBy('lesson_component_id');
                    if ($scorer->incompleteRequired($lesson, $components) !== []) {
                        continue;
                    }
                    $lessonRepairs++;
                    if (! $apply) {
                        continue;
                    }
                    DB::transaction(function () use ($progress, $lesson, $components, $scorer) {
                        $completedAt = $progress->componentProgress->where('status', 'complete')->max('updated_at') ?? now();
                        $progress->update([
                            'status' => 'completed', 'score' => $scorer->score($lesson, $components),
                            'components_completed' => $progress->componentProgress->where('status', 'complete')->count(),
                            'completed_at' => $completedAt,
                        ]);
                        $node = LearnerPathNode::whereHas('enrollment', fn ($q) => $q->where('learner_profile_id', $progress->learner_profile_id))
                            ->where('lesson_id', $lesson->id)->first();
                        if ($node) {
                            $node->update(['state' => 'completed']);
                            LearnerPathNode::where('enrollment_id', $node->enrollment_id)->where('position', '>', $node->position)
                                ->orderBy('position')->where('state', 'locked')->first()?->update(['state' => 'active']);
                        }
                    });
                }
            });

        $firstSteps = Badge::where('code', 'first_lesson')->first();
        if (! $firstSteps) {
            $this->error('First Steps badge is missing. Seed badges before applying repairs.');

            return self::FAILURE;
        }
        $completedByLearner = DB::table('lesson_progress')->where('status', 'completed')
            ->select('learner_profile_id')->selectRaw('MIN(completed_at) AS first_completed_at')
            ->selectRaw('MAX(completed_at) AS last_completed_at')->groupBy('learner_profile_id')->get();
        foreach ($completedByLearner as $row) {
            $hasBadge = LearnerBadge::where('learner_profile_id', $row->learner_profile_id)->where('badge_id', $firstSteps->id)->exists();
            $hasStreak = Streak::where('learner_profile_id', $row->learner_profile_id)->exists();
            $badgeRepairs += (int) ! $hasBadge;
            $streakRepairs += (int) ! $hasStreak;
            if (! $apply) {
                continue;
            }
            DB::transaction(function () use ($row, $firstSteps, $hasBadge, $hasStreak) {
                $firstAt = $row->first_completed_at ? Carbon::parse($row->first_completed_at) : now();
                $lastAt = $row->last_completed_at ? Carbon::parse($row->last_completed_at) : $firstAt;
                if (! $hasBadge) {
                    LearnerBadge::firstOrCreate(['learner_profile_id' => $row->learner_profile_id, 'badge_id' => $firstSteps->id],
                        ['earned_at' => $firstAt]);
                }
                if (! $hasStreak) {
                    $current = $lastAt->isToday() ? 1 : 0;
                    Streak::firstOrCreate(['learner_profile_id' => $row->learner_profile_id], [
                        'current_count' => $current, 'longest_count' => 1,
                        'last_active_date' => $lastAt->toDateString(), 'state' => 'active',
                    ]);
                }
            });
        }

        $monthlyBadge = Badge::where('code', 'streak_30')->first();
        if ($monthlyBadge) {
            Streak::where('longest_count', '>=', 30)->orderBy('id')->chunkById(100, function ($rows) use ($monthlyBadge, $apply, &$monthlyRepairs) {
                foreach ($rows as $streak) {
                    if (LearnerBadge::where('learner_profile_id', $streak->learner_profile_id)
                        ->where('badge_id', $monthlyBadge->id)->exists()) {
                        continue;
                    }
                    $monthlyRepairs++;
                    if ($apply) {
                        LearnerBadge::firstOrCreate(['learner_profile_id' => $streak->learner_profile_id,
                            'badge_id' => $monthlyBadge->id], ['earned_at' => $streak->last_active_date ?? now()]);
                    }
                }
            });
        }

        $this->line(($apply ? 'Applied' : 'Dry run').": lessons={$lessonRepairs}, first_steps={$badgeRepairs}, missing_streaks={$streakRepairs}, monthly_badges={$monthlyRepairs}");

        return self::SUCCESS;
    }
}
