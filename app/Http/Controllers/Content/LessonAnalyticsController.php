<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\ComponentProgress;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuestionResponse;
use Illuminate\Http\JsonResponse;

class LessonAnalyticsController extends Controller
{
    /**
     * Lesson drop-off funnel + per-question accuracy, aggregated from the
     * learner progress ledgers. Powers the authoring "Insights" view so content
     * owners can see where learners quit and which questions trip them up.
     */
    public function show(Lesson $lesson): JsonResponse
    {
        $lesson->load([
            'components' => fn ($q) => $q->orderBy('position'),
            'components.quiz.questions' => fn ($q) => $q->orderBy('position'),
        ]);

        $lessonProgress = LessonProgress::where('lesson_id', $lesson->id);
        $learnersStarted = (clone $lessonProgress)->count();
        $learnersCompleted = (clone $lessonProgress)->where('status', 'completed')->count();
        $progressIds = (clone $lessonProgress)->pluck('id');

        // Per-component: how many learners reached it vs completed it.
        $byComponent = ComponentProgress::whereIn('lesson_progress_id', $progressIds)
            ->selectRaw("lesson_component_id, count(*) as reached, sum(case when status = 'complete' then 1 else 0 end) as completed")
            ->groupBy('lesson_component_id')
            ->get()
            ->keyBy('lesson_component_id');

        $funnel = $lesson->components->map(function ($c) use ($byComponent) {
            $row = $byComponent->get($c->id);

            return [
                'component_id' => $c->id,
                'type' => $c->type,
                'title' => $c->title,
                'position' => $c->position,
                'reached' => (int) ($row->reached ?? 0),
                'completed' => (int) ($row->completed ?? 0),
            ];
        })->values();

        // Per-question accuracy across every quiz in the lesson.
        $questionIds = $lesson->components
            ->flatMap(fn ($c) => $c->quiz ? $c->quiz->questions->pluck('id') : collect());

        $byQuestion = QuestionResponse::whereIn('question_id', $questionIds)
            ->selectRaw('question_id, count(*) as answered, sum(is_correct) as correct')
            ->groupBy('question_id')
            ->get()
            ->keyBy('question_id');

        $questions = [];
        foreach ($lesson->components as $c) {
            if ($c->quiz === null) {
                continue;
            }
            foreach ($c->quiz->questions as $q) {
                $row = $byQuestion->get($q->id);
                $answered = (int) ($row->answered ?? 0);
                $correct = (int) ($row->correct ?? 0);
                $questions[] = [
                    'question_id' => $q->id,
                    'prompt' => $q->prompt,
                    'type' => $q->type,
                    'answered' => $answered,
                    'correct' => $correct,
                    'accuracy' => $answered > 0 ? round($correct / $answered, 2) : null,
                ];
            }
        }

        return response()->json(['data' => [
            'lesson' => ['id' => $lesson->id, 'title' => $lesson->title],
            'learners_started' => $learnersStarted,
            'learners_completed' => $learnersCompleted,
            'funnel' => $funnel,
            'questions' => $questions,
        ]]);
    }
}
