<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Services\Learning\LessonAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LibraryLessonController extends Controller
{
    public function index(Request $request, LessonAccess $access): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'in:proverb,trivia'],
            'learner_id' => ['nullable', 'integer', 'exists:learner_profiles,id'],
            'language' => ['nullable', 'string', 'max:10'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $author = $request->user()->can('content.courses.view') && ! $request->filled('learner_id');
        $learner = null;
        if (! $author) {
            $learner = LearnerProfile::findOrFail($request->integer('learner_id'));
            Gate::authorize('view', $learner);
        }

        $query = Lesson::query()->with(['courseLevel.course.language', 'components.exercise.flashcards', 'components.quiz.questions'])
            ->where('kind', $validated['kind'])
            ->whereHas('courseLevel.course', fn ($q) => $author ? $q : $q->where('is_published', true));
        if (! $author) {
            $query->whereNotNull('published_at');
        }
        if ($request->filled('language')) {
            $query->whereHas('courseLevel.course.language', fn ($q) => $q->where('code', $validated['language']));
        }
        if ($request->filled('q')) {
            $term = $validated['q'];
            $query->where('title', 'like', "%{$term}%");
        }
        $page = $query->orderByDesc('created_at')->paginate(20);
        $tier = $learner ? $access->tier($learner) : null;

        return response()->json([
            'data' => collect($page->items())->map(function (Lesson $lesson) use ($learner, $access, $tier) {
                $permission = $learner ? $access->content($learner, $lesson, $tier) : null;
                $proverbCount = 0;
                $questionCount = 0;
                $timeLimitSeconds = null;
                $hasAudio = false;
                $sampleProverb = null;
                $sampleMeaning = null;
                foreach ($lesson->components as $component) {
                    if ($component->exercise) {
                        $cards = $component->exercise->flashcards;
                        $proverbCount += $cards->count();
                        $hasAudio = $hasAudio || $cards->contains(fn ($card) => $card->audio_asset_id !== null);
                        $sampleProverb ??= $cards->first()?->front_text;
                        $sampleMeaning ??= $cards->first()?->back_text;
                    }
                    if ($component->quiz) {
                        $questionCount += $component->quiz->questions->count();
                        $timeLimitSeconds ??= $component->quiz->time_limit_seconds;
                    }
                }

                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'kind' => $lesson->kind,
                    'course_id' => $lesson->courseLevel->course_id,
                    'course_title' => $lesson->courseLevel->course->title,
                    'level_title' => $lesson->courseLevel->title,
                    'language' => $lesson->courseLevel->course->language->code,
                    'est_minutes' => $lesson->est_minutes,
                    'is_published' => $lesson->published_at !== null,
                    'access_reason' => $permission['reason'] ?? null,
                    'proverb_count' => $proverbCount,
                    'question_count' => $questionCount,
                    'time_limit_seconds' => $timeLimitSeconds,
                    'has_audio' => $hasAudio,
                    'sample_proverb' => $sampleProverb,
                    'sample_meaning' => $sampleMeaning,
                ];
            })->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
