<?php

namespace App\Services\Learning;

use App\Models\QuizAttempt;
use App\Models\SpeakingSubmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Recorded learning only. No recording or AI-scoring side effects. */
class LearningAnalytics
{
    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, array{learner_id: int, lesson_targets: int, lessons_completed: int, quiz_scored: int, quiz_score_sum: float, speaking_scored: int, speaking_score_sum: float}>
     */
    public function byLearner(Collection $ids): Collection
    {
        // One target per enrolled learner/published lesson. An unstarted target
        // counts in the denominator; progress outside these courses does not.
        $targets = DB::table('enrollments')->whereIn('enrollments.learner_profile_id', $ids)
            ->whereIn('enrollments.status', ['active', 'completed'])
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')->where('courses.is_published', true)
            ->join('course_levels', 'course_levels.course_id', '=', 'courses.id')
            ->join('lessons', 'lessons.course_level_id', '=', 'course_levels.id')->whereNotNull('lessons.published_at')
            ->leftJoin('lesson_progress', function ($join) {
                $join->on('lesson_progress.lesson_id', '=', 'lessons.id')->on('lesson_progress.learner_profile_id', '=', 'enrollments.learner_profile_id');
            })->selectRaw("enrollments.learner_profile_id, COUNT(*) as targets, SUM(CASE WHEN lesson_progress.status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->groupBy('enrollments.learner_profile_id')->get()->keyBy('learner_profile_id');
        $quiz = QuizAttempt::whereIn('learner_profile_id', $ids)->whereNotNull('completed_at')->whereNotNull('score')
            ->selectRaw('learner_profile_id, COUNT(*) as scored, SUM(score) as score_sum')->groupBy('learner_profile_id')->get()->keyBy('learner_profile_id');
        $speech = SpeakingSubmission::whereIn('learner_profile_id', $ids)
            ->selectRaw('learner_profile_id, COUNT(ai_score) as scored, SUM(ai_score) as score_sum')->groupBy('learner_profile_id')->get()->keyBy('learner_profile_id');

        return $ids->mapWithKeys(fn ($id) => [$id => [
            'learner_id' => $id,
            'lesson_targets' => (int) ($targets[$id]->targets ?? 0),
            'lessons_completed' => (int) ($targets[$id]->completed ?? 0),
            'quiz_scored' => (int) ($quiz[$id]->scored ?? 0),
            'quiz_score_sum' => (float) ($quiz[$id]->score_sum ?? 0),
            'speaking_scored' => (int) ($speech[$id]->scored ?? 0),
            'speaking_score_sum' => (float) ($speech[$id]->score_sum ?? 0),
        ]]);
    }

    /**
     * @param  Collection<int, array{learner_id: int, lesson_targets: int, lessons_completed: int, quiz_scored: int, quiz_score_sum: float, speaking_scored: int, speaking_score_sum: float}>  $rows
     * @return array<string, mixed>
     */
    public function summarize(Collection $rows): array
    {
        $targets = (int) $rows->sum('lesson_targets');
        $completed = (int) $rows->sum('lessons_completed');
        $quizCount = (int) $rows->sum('quiz_scored');
        $speechCount = (int) $rows->sum('speaking_scored');

        return [
            'lesson_targets' => $targets, 'lessons_completed' => $completed,
            'completion_rate' => $targets > 0 ? round($completed / $targets * 100, 1) : null,
            'quiz_scored' => $quizCount,
            'avg_quiz_score' => $quizCount > 0 ? round($rows->sum('quiz_score_sum') / $quizCount * 100, 1) : null,
            'speaking_scored' => $speechCount,
            // The deferred scorer has no recorded scale contract. Preserve
            // stored units instead of incorrectly labelling them percent.
            'avg_speaking_score' => $speechCount > 0 ? round($rows->sum('speaking_score_sum') / $speechCount, 2) : null,
        ];
    }
}
