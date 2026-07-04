<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreProgressRequest;
use App\Models\ComponentProgress;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\LessonComponent;
use App\Services\Learning\XapiRecorder;
use Illuminate\Http\JsonResponse;

class ProgressController extends Controller
{
    use ResolvesLearner;

    /** Per-learner progress summary. Authorized by route can:view,learner. */
    public function show(LearnerProfile $learner): JsonResponse
    {
        $progress = $learner->lessonProgress()
            ->get(['id', 'lesson_id', 'status', 'score', 'components_completed', 'completed_at']);

        return response()->json(['data' => ['lessons' => $progress]]);
    }

    /**
     * Heartbeat / completion for non-graded components (video, exercise, game,
     * assignment). Quiz completion flows through AnswerController.
     */
    public function store(StoreProgressRequest $request, Lesson $lesson, XapiRecorder $xapi): JsonResponse
    {
        $learner = $this->learner($request->integer('learner_id'));
        $component = LessonComponent::findOrFail($request->integer('component_id'));

        abort_unless($component->lesson_id === $lesson->id, 422, 'Component does not belong to this lesson.');

        $progress = $this->lessonProgress($learner, $lesson);

        $cp = ComponentProgress::firstOrCreate(
            ['lesson_progress_id' => $progress->id, 'lesson_component_id' => $component->id],
            ['status' => 'in_progress'],
        );

        $data = $cp->data ?? [];

        // Cumulative watch time + play count accumulate from per-beat deltas; the
        // playhead position and length are absolute snapshots.
        if ($request->filled('watched_delta')) {
            $data['watched_seconds'] = ($data['watched_seconds'] ?? 0) + (int) round((float) $request->input('watched_delta'));
        } elseif ($request->filled('watched_seconds')) {
            $data['watched_seconds'] = $request->integer('watched_seconds');
        }
        if ($request->filled('play_delta')) {
            $data['play_count'] = ($data['play_count'] ?? 0) + $request->integer('play_delta');
        }
        if ($request->filled('position_seconds')) {
            $data['position_seconds'] = round((float) $request->input('position_seconds'), 2);
        }
        if ($request->filled('duration_seconds')) {
            $data['duration_seconds'] = round((float) $request->input('duration_seconds'), 2);
        }
        $event = $request->input('event');
        if ($event) {
            $data['last_event'] = $event;
        }

        if ($request->boolean('completed')) {
            $cp->status = 'complete';
            $cp->score = 1.0;
        }
        $cp->data = $data;
        $cp->save();

        $progress->update([
            'components_completed' => $progress->componentProgress()->where('status', 'complete')->count(),
        ]);

        $this->recordStatement($xapi, $learner->id, $component, $cp, $data, $event);

        return response()->json(['data' => [
            'component_id' => $component->id,
            'status' => $cp->status,
            'watched_seconds' => $data['watched_seconds'] ?? 0,
            'play_count' => $data['play_count'] ?? 0,
            'position_seconds' => $data['position_seconds'] ?? 0,
        ]]);
    }

    /**
     * Emit the matching xAPI statement. Discrete video events (play/pause/seek/
     * complete) become Video Profile statements with time/progress extensions;
     * periodic heartbeats only persist aggregates (no statement, to avoid spam);
     * non-video components fall back to experienced/completed.
     *
     * @param  array<string, mixed>  $data
     */
    private function recordStatement(
        XapiRecorder $xapi,
        int $learnerId,
        LessonComponent $component,
        ComponentProgress $cp,
        array $data,
        ?string $event,
    ): void {
        $videoVerbs = [
            'played' => XapiRecorder::VERB_PLAYED,
            'paused' => XapiRecorder::VERB_PAUSED,
            'seeked' => XapiRecorder::VERB_SEEKED,
            'completed' => XapiRecorder::VERB_COMPLETED,
        ];

        if ($event === 'heartbeat') {
            return; // aggregates already persisted; no statement
        }

        if ($event !== null && isset($videoVerbs[$event])) {
            $ext = [];
            if (isset($data['position_seconds'])) {
                $ext[XapiRecorder::EXT_TIME] = $data['position_seconds'];
            }
            if (! empty($data['duration_seconds'])) {
                $ext[XapiRecorder::EXT_LENGTH] = $data['duration_seconds'];
                $ext[XapiRecorder::EXT_PROGRESS] = round(min(1, ($data['watched_seconds'] ?? 0) / $data['duration_seconds']), 3);
            }
            if (isset($data['play_count'])) {
                $ext[XapiRecorder::EXT_PLAY_COUNT] = $data['play_count'];
            }

            $result = ['extensions' => $ext];
            if (isset($data['watched_seconds'])) {
                $result['duration'] = 'PT'.$data['watched_seconds'].'S';
            }
            if ($event === 'completed') {
                $result['completion'] = true;
            }

            $xapi->record(
                $learnerId,
                $videoVerbs[$event],
                'components',
                $component->id,
                $component->title ?? $component->type,
                XapiRecorder::ACTIVITY_MEDIA,
                $result,
            );

            return;
        }

        // Non-video heartbeat/completion (exercise, game, assignment).
        $xapi->record(
            $learnerId,
            $cp->status === 'complete' ? XapiRecorder::VERB_COMPLETED : XapiRecorder::VERB_EXPERIENCED,
            'components',
            $component->id,
            $component->title ?? $component->type,
            XapiRecorder::ACTIVITY_MEDIA,
            $cp->status === 'complete' ? ['completion' => true] : [],
        );
    }
}
