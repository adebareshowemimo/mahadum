<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\CourseLevel;
use App\Models\Lesson;
use App\Services\Content\LessonPublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
