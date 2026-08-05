<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ReorderLessonsRequest;
use App\Http\Requests\Content\StoreLessonRequest;
use App\Http\Requests\Content\UpdateLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\CourseLevel;
use App\Models\Lesson;
use App\Services\Content\LessonPublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    public function index(Request $request, CourseLevel $level): AnonymousResourceCollection
    {
        $query = $level->lessons()->orderBy('position');

        if (! $request->user()->can('content.lessons.manage')) {
            $query->whereNotNull('published_at'); // learners see published only
        }

        return LessonResource::collection($query->get());
    }

    public function store(StoreLessonRequest $request, CourseLevel $level): JsonResponse
    {
        $position = $request->input('position')
            ?? (($level->lessons()->max('position') ?? 0) + 1);

        $lesson = $level->lessons()->create([
            'title' => $request->string('title'),
            'position' => $position,
            'est_minutes' => $request->input('est_minutes', 5),
            'is_locked_by_default' => $request->boolean('is_locked_by_default', true),
        ]);

        return (new LessonResource($lesson))->response()->setStatusCode(201);
    }

    public function show(Lesson $lesson): LessonResource
    {
        $lesson->load([
            'components' => fn ($q) => $q->orderBy('position'),
            'components.video.sourceAsset',
            'components.quiz.questions.options',
            'components.quiz.questions.promptAudioAsset',
            'components.quiz.questions.promptImageAsset',
            'components.speakingPrompt',
            'components.assignment',
            'components.exercise.flashcards.audioAsset',
            'components.exercise.flashcards.imageAsset',
            'components.game',
        ]);

        return new LessonResource($lesson);
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): LessonResource
    {
        $lesson->update($request->validated());

        return new LessonResource($lesson);
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        $lesson->delete();

        return response()->json(null, 204);
    }

    /**
     * Persist a new lesson order within the level. `order` is the full list
     * of lesson ids in their desired sequence; positions are reassigned 1..n.
     */
    public function reorder(ReorderLessonsRequest $request, CourseLevel $level): AnonymousResourceCollection
    {
        DB::transaction(function () use ($request, $level) {
            foreach ($request->input('order') as $index => $lessonId) {
                $level->lessons()->whereKey($lessonId)->update(['position' => $index + 1]);
            }
        });

        return LessonResource::collection($level->lessons()->orderBy('position')->get());
    }

    public function publish(Lesson $lesson, LessonPublishService $publisher): JsonResponse
    {
        $failures = $publisher->failures($lesson);

        if (! empty($failures)) {
            return response()->json([
                'error' => [
                    'code' => 'publish_checks_failed',
                    'message' => 'This lesson does not meet the publish requirements.',
                    'status' => 422,
                    'details' => $failures,
                ],
            ], 422);
        }

        $lesson->forceFill([
            'published_at' => now(),
            'is_locked_by_default' => false,
        ])->save();

        return (new LessonResource($lesson->fresh()))->response()->setStatusCode(200);
    }
}
