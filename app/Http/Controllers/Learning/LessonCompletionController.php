<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\CompleteLessonRequest;
use App\Models\LearnerPathNode;
use App\Models\Lesson;
use App\Models\XpLedger;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\StreakService;
use App\Services\Learning\LessonScorer;
use App\Services\Learning\XapiRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LessonCompletionController extends Controller
{
    use ResolvesLearner;

    public function complete(
        CompleteLessonRequest $request,
        Lesson $lesson,
        LessonScorer $scorer,
        StreakService $streaks,
        BadgeService $badges,
        XapiRecorder $xapi,
    ): JsonResponse {
        $learner = $this->learner($request->integer('learner_id'));
        $lesson->load('components');

        $progress = $this->lessonProgress($learner, $lesson);
        $byComponent = $progress->componentProgress()->get()->keyBy('lesson_component_id');

        $incomplete = $scorer->incompleteRequired($lesson, $byComponent);
        if (! empty($incomplete)) {
            return response()->json([
                'error' => [
                    'code' => 'lesson_incomplete',
                    'message' => 'Finish all required components before completing the lesson.',
                    'status' => 422,
                    'details' => ['incomplete_component_ids' => $incomplete],
                ],
            ], 422);
        }

        $alreadyDone = $progress->status === 'completed';
        $score = $scorer->score($lesson, $byComponent);

        $result = DB::transaction(function () use ($lesson, $learner, $progress, $byComponent, $score, $alreadyDone) {
            $progress->update([
                'status' => 'completed',
                'score' => $score,
                'components_completed' => $byComponent->where('status', 'complete')->count(),
                'completed_at' => $progress->completed_at ?? now(),
            ]);

            // XP awarded once per lesson (idempotent on re-complete).
            $xpTotal = (int) $lesson->components->sum('xp_value');
            if (! $alreadyDone && $xpTotal > 0) {
                XpLedger::create([
                    'learner_profile_id' => $learner->id,
                    'amount' => $xpTotal,
                    'source' => 'lesson',
                    'reference_type' => Lesson::class,
                    'reference_id' => $lesson->id,
                ]);
            }

            return ['xp_total' => $alreadyDone ? 0 : $xpTotal, 'next_node' => $this->unlockNext($learner->id, $lesson->id)];
        });

        if (! $alreadyDone) {
            $xapi->record($learner->id, XapiRecorder::VERB_COMPLETED, 'lessons', $lesson->id, $lesson->title, XapiRecorder::ACTIVITY_LESSON, [
                'completion' => true,
                'score' => ['scaled' => round($score, 4)],
            ]);
        }

        // Gamification: completing a lesson is qualifying streak activity and may
        // unlock badges. Recorded after the lesson is finalized.
        $streak = $streaks->recordActivity($learner);
        $badgesUnlocked = $badges->evaluate($learner);

        return response()->json(['data' => [
            'lesson_score' => $score,
            'xp_total' => $result['xp_total'],
            'streak' => ['count' => $streak->current_count, 'state' => $streak->state],
            'badges_unlocked' => $badgesUnlocked,
            'next_node' => $result['next_node'],
        ]]);
    }

    /**
     * Mark this lesson's node completed and activate the next locked node in the
     * same enrollment. Returns the unlocked node (or null at end of path).
     */
    private function unlockNext(int $learnerId, int $lessonId): ?array
    {
        $node = LearnerPathNode::whereHas('enrollment', fn ($q) => $q->where('learner_profile_id', $learnerId))
            ->where('lesson_id', $lessonId)
            ->first();

        if (! $node) {
            return null;
        }

        $node->update(['state' => 'completed']);

        $next = LearnerPathNode::where('enrollment_id', $node->enrollment_id)
            ->where('position', '>', $node->position)
            ->orderBy('position')
            ->first();

        if ($next && $next->state === 'locked') {
            $next->update(['state' => 'active']);
        }

        return $next ? ['lesson_id' => $next->lesson_id, 'unlocked' => true] : null;
    }
}
