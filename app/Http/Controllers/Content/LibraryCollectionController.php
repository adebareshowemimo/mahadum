<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\LibrarySet;
use App\Models\LibrarySetLevel;
use App\Services\AuditLogger;
use App\Services\Content\LessonPublishService;
use App\Services\Learning\LessonAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LibraryCollectionController extends Controller
{
    public function index(Request $request, LessonAccess $access): JsonResponse
    {
        $values = $request->validate([
            'kind' => ['nullable', 'in:proverb,trivia'],
            'language' => ['nullable', 'string', 'max:10'],
            'q' => ['nullable', 'string', 'max:100'],
            'learner_id' => ['nullable', 'integer', 'exists:learner_profiles,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $author = $request->user()->can('content.courses.view') && ! $request->filled('learner_id');
        $learner = $author ? null : LearnerProfile::findOrFail($request->integer('learner_id'));
        if ($learner) {
            Gate::authorize('view', $learner);
        }
        $query = LibrarySet::with(['language', 'levels.lessons'])
            ->when(! $author, fn ($q) => $q->where('status', 'published'))
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $values['kind']))
            ->when($request->filled('language'), fn ($q) => $q->whereHas('language', fn ($l) => $l->where('code', $values['language'])))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$values['q'].'%'));
        $page = $query->latest()->paginate(12);

        return response()->json([
            'data' => collect($page->items())->map(fn (LibrarySet $set) => $this->serialize($set, $learner, $access, false))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, LibrarySet $set, LessonAccess $access): JsonResponse
    {
        $author = $request->user()->can('content.courses.view') && ! $request->filled('learner_id');
        $learner = $author ? null : LearnerProfile::findOrFail($request->integer('learner_id'));
        if ($learner) {
            Gate::authorize('view', $learner);
            abort_unless($set->status === 'published', 404);
        }
        $set->load(['language', 'levels.lessons']);

        return response()->json(['data' => $this->serialize($set, $learner, $access, true)]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $values = $request->validate([
            'kind' => ['required', 'in:proverb,trivia'], 'language_id' => ['required', 'integer', 'exists:languages,id'],
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $set = LibrarySet::create($values + ['owner_user_id' => $request->user()->id, 'status' => 'draft']);
        $audit->record('library_set.created', $set, [], $values);

        return response()->json(['data' => $this->serialize($set->load('language'), null, app(LessonAccess::class), true)], 201);
    }

    public function update(Request $request, LibrarySet $set, AuditLogger $audit): JsonResponse
    {
        $values = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'language_id' => ['sometimes', 'integer', 'exists:languages,id'], 'kind' => ['prohibited'],
        ]);
        $before = $set->only(array_keys($values));
        $set->update($values);
        if (isset($values['language_id'])) {
            Lesson::whereHas('librarySetLevel', fn ($q) => $q->where('library_set_id', $set->id))
                ->update(['language_id' => $set->language_id]);
        }
        $audit->record('library_set.updated', $set, $before, $values);

        return $this->show($request, $set, app(LessonAccess::class));
    }

    public function destroy(LibrarySet $set, AuditLogger $audit): JsonResponse
    {
        DB::transaction(function () use ($set) {
            foreach ($set->levels as $level) {
                foreach ($level->lessons as $lesson) {
                    $lesson->delete();
                }
            }
            $set->delete();
        });
        $audit->record('library_set.deleted', $set, ['title' => $set->title]);

        return response()->json(null, 204);
    }

    public function addLevel(Request $request, LibrarySet $set): JsonResponse
    {
        $values = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'is_free' => ['sometimes', 'boolean'],
        ]);
        $position = ((int) $set->levels()->max('position')) + 1;
        $level = $set->levels()->create(['title' => $values['title'], 'position' => $position,
            'is_free' => $values['is_free'] ?? true]);
        app(AuditLogger::class)->record('library_level.created', $level, [], $values);

        return response()->json(['data' => $this->level($level)], 201);
    }

    public function updateLevel(Request $request, LibrarySetLevel $level): JsonResponse
    {
        $values = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'], 'is_free' => ['sometimes', 'boolean'],
        ]);
        $before = $level->only(array_keys($values));
        $level->update($values);
        app(AuditLogger::class)->record('library_level.updated', $level, $before, $values);

        return response()->json(['data' => $this->level($level)]);
    }

    public function deleteLevel(LibrarySetLevel $level): JsonResponse
    {
        abort_if($level->lessons()->exists(), 409, 'Delete or move this level’s lessons first.');
        $level->delete();

        return response()->json(null, 204);
    }

    public function reorderLevels(Request $request, LibrarySet $set): JsonResponse
    {
        $values = $request->validate(['order' => ['required', 'array', 'min:1'], 'order.*' => ['integer', Rule::exists('library_set_levels', 'id')->where('library_set_id', $set->id)]]);
        abort_unless(count($values['order']) === $set->levels()->count()
            && count(array_unique($values['order'])) === count($values['order']), 422, 'Include each level once.');
        DB::transaction(function () use ($set, $values) {
            // Temporary positions avoid the per-set uniqueness constraint while swapping.
            $offset = ((int) $set->levels()->max('position')) + count($values['order']) + 1;
            foreach ($values['order'] as $index => $id) {
                $set->levels()->whereKey($id)->update(['position' => $offset + $index]);
            }
            foreach ($values['order'] as $index => $id) {
                $set->levels()->whereKey($id)->update(['position' => $index + 1]);
                Lesson::where('library_set_level_id', $id)->update(['standalone_level' => $index + 1]);
            }
        });

        return $this->show(request(), $set->fresh(), app(LessonAccess::class));
    }

    public function addLesson(Request $request, LibrarySetLevel $level): JsonResponse
    {
        $values = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'summary' => ['nullable', 'string', 'max:1000'],
            'est_minutes' => ['nullable', 'integer', 'min:1'],
        ]);
        $set = $level->set;
        $lesson = $level->lessons()->create([
            'title' => $values['title'], 'summary' => $values['summary'] ?? null,
            'est_minutes' => $values['est_minutes'] ?? 5, 'position' => ((int) $level->lessons()->max('position')) + 1,
            'kind' => $set->kind, 'language_id' => $set->language_id, 'standalone_level' => $level->position,
            'course_level_id' => null, 'is_locked_by_default' => false,
        ]);

        return response()->json(['data' => ['id' => $lesson->id, 'title' => $lesson->title]], 201);
    }

    public function reorderLessons(Request $request, LibrarySetLevel $level): JsonResponse
    {
        $values = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer', Rule::exists('lessons', 'id')->where('library_set_level_id', $level->id)]]);
        abort_unless(count($values['order']) === $level->lessons()->count()
            && count(array_unique($values['order'])) === count($values['order']), 422, 'Include each lesson once.');
        DB::transaction(function () use ($level, $values) {
            foreach ($values['order'] as $index => $id) {
                $level->lessons()->whereKey($id)->update(['position' => $index + 1]);
            }
        });

        return response()->json(['data' => $this->level($level->fresh())]);
    }

    public function publish(LibrarySet $set, LessonPublishService $publisher): JsonResponse
    {
        $lessons = Lesson::whereHas('librarySetLevel', fn ($q) => $q->where('library_set_id', $set->id))->get();
        $failures = [];
        if ($lessons->isEmpty()) {
            $failures[] = 'Add a lesson before publishing this set.';
        }
        foreach ($lessons as $lesson) {
            foreach ($publisher->failures($lesson) as $reason) {
                $failures[] = $lesson->title.': '.$reason;
            }
        }
        if ($failures !== []) {
            return response()->json(['error' => ['code' => 'publish_checks_failed', 'message' => 'Complete the listed lessons before publishing this set.',
                'status' => 422, 'details' => $failures]], 422);
        }
        DB::transaction(function () use ($set, $lessons) {
            foreach ($lessons as $lesson) {
                $lesson->update(['published_at' => $lesson->published_at ?? now()]);
            }
            $set->update(['status' => 'published']);
        });
        app(AuditLogger::class)->record('library_set.published', $set, [], ['lessons' => $lessons->count()]);

        return $this->show(request(), $set->fresh(), app(LessonAccess::class));
    }

    public function unpublish(LibrarySet $set): JsonResponse
    {
        $set->update(['status' => 'draft']);

        return $this->show(request(), $set, app(LessonAccess::class));
    }

    private function serialize(LibrarySet $set, ?LearnerProfile $learner, LessonAccess $access, bool $withLevels): array
    {
        $set->loadMissing('language');
        $levels = $withLevels ? $set->levels()->with('lessons')->get() : collect();
        $lessons = $withLevels ? $levels->flatMap->lessons : $set->levels->flatMap->lessons;
        $published = $lessons->whereNotNull('published_at');
        $completed = $learner ? DB::table('lesson_progress')->where('learner_profile_id', $learner->id)
            ->where('status', 'completed')->whereIn('lesson_id', $published->pluck('id'))->pluck('lesson_id')->all() : [];
        $tier = $learner ? $access->tier($learner) : null;

        return [
            'id' => $set->id, 'kind' => $set->kind, 'title' => $set->title,
            'description' => $set->description, 'language_id' => $set->language_id,
            'language' => $set->language?->code, 'status' => $set->status,
            'levels_count' => $set->levels->count(), 'lessons_count' => $published->count(),
            'completed_count' => count($completed),
            'levels' => $withLevels ? $levels->map(fn (LibrarySetLevel $level) => $this->level($level, $learner, $access, $tier, $completed))->values() : null,
        ];
    }

    private function level(LibrarySetLevel $level, ?LearnerProfile $learner = null, ?LessonAccess $access = null, ?string $tier = null, array $completed = []): array
    {
        $lessons = $level->lessons;
        if ($learner) {
            $lessons = $lessons->whereNotNull('published_at');
        }

        return ['id' => $level->id, 'title' => $level->title, 'position' => $level->position,
            'is_free' => $level->is_free, 'lessons' => $lessons->map(fn (Lesson $lesson) => [
                'id' => $lesson->id, 'title' => $lesson->title,
                'summary' => $learner && $access->content($learner, $lesson, $tier)['reason'] !== null ? null : $lesson->summary,
                'position' => $lesson->position, 'published_at' => $lesson->published_at,
                'completed' => in_array($lesson->id, $completed, true),
                'access_reason' => $learner ? ($access->content($learner, $lesson, $tier)['reason'] ?? null) : null,
            ])->values()];
    }
}
