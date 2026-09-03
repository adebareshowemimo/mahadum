<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreCourseRequest;
use App\Http\Requests\Content\UpdateCourseRequest;
use App\Http\Resources\CourseLevelResource;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Services\AuditLogger;
use App\Services\Content\LessonPublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    /** Fields tracked in course.updated audit diffs — content, not admin/status flags handled elsewhere. */
    private const AUDITED_FIELDS = ['title', 'description', 'level_band', 'language_id'];

    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 12), 1), 100);
        $query = Course::query()->with(['language', 'ownerUser'])->withCount('levels');

        // Learners only see published courses; CMS roles see drafts too.
        if (! $request->user()->can('content.courses.view')) {
            $query->where('is_published', true);
        }

        // Learner-facing catalogues need enrollment state so the UI can show
        // genuinely available courses instead of offering the same course
        // again. Authorize the requested profile before using it in the query.
        if ($request->filled('learner_id')) {
            $learner = LearnerProfile::findOrFail($request->integer('learner_id'));
            Gate::authorize('view', $learner);
            $query->withExists([
                'enrollments as is_enrolled' => fn ($q) => $q->where('learner_profile_id', $learner->id),
            ]);
        }

        if ($request->filled('language')) {
            $query->whereHas('language', fn ($q) => $q->where('code', $request->query('language')));
        }

        if ($request->filled('level')) {
            $query->where('level_band', $request->query('level'));
        }

        // Admin oversight filters (only meaningful for CMS roles that see drafts).
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($courses) => $courses
                ->where('title', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));
        }

        $levels = Course::query()
            ->where('is_published', true)
            ->whereNotNull('level_band')
            ->where('level_band', '!=', '')
            ->distinct()
            ->orderBy('level_band')
            ->pluck('level_band')
            ->values();

        return CourseResource::collection($query->latest()->paginate($perPage))
            ->additional(['filters' => ['levels' => $levels]]);
    }

    public function show(Course $course): CourseResource
    {
        $course->load(['language', 'levels' => fn ($q) => $q->orderBy('position')]);

        return new CourseResource($course);
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = Course::create($request->validated() + [
            'owner_user_id' => $request->user()->id,
            'status' => 'draft',
            'is_published' => false,
        ]);

        $this->audit->record(
            'course.created',
            $course,
            [],
            $course->only(self::AUDITED_FIELDS),
        );

        return (new CourseResource($course->load('language')))
            ->response()->setStatusCode(201);
    }

    public function update(UpdateCourseRequest $request, Course $course): CourseResource
    {
        $before = $course->only(self::AUDITED_FIELDS);
        $course->update($request->validated());

        $this->audit->record(
            'course.updated',
            $course,
            $before,
            $course->only(self::AUDITED_FIELDS),
        );

        return new CourseResource($course->load('language'));
    }

    public function destroy(Course $course): JsonResponse
    {
        $before = $course->only([...self::AUDITED_FIELDS, 'status']);
        $course->delete();

        $this->audit->record('course.deleted', $course, $before);

        return response()->json(null, 204);
    }

    /**
     * Publish a course (make it visible to learners). Publish-rule: the course
     * must contain at least one published lesson, else there is nothing to learn.
     */
    /**
     * Publish a course and everything in it. Publishing at course level is the
     * author's "ship it" switch: every draft lesson goes live with the course,
     * so nobody has to walk the tree publishing lessons one at a time.
     *
     * Deliberately unconditional — the per-lesson readiness checks in
     * LessonPublishService still gate the *lesson-level* publish button
     * (LessonController::publish), where an author is working on one lesson and
     * wants to know what's missing. At course level the decision has already
     * been made, so an incomplete lesson ships rather than silently staying
     * behind; `meta.lessons_incomplete` names any that went out below the §6 bar
     * so the author can follow up.
     */
    public function publish(Course $course, LessonPublishService $publisher): JsonResponse
    {
        $lessons = Lesson::whereHas('courseLevel', fn ($q) => $q->where('course_id', $course->id))
            ->with(['components.video', 'components.quiz.questions.options'])
            ->get();

        $publishedNow = [];
        $incomplete = [];

        foreach ($lessons->whereNull('published_at') as $lesson) {
            // Recorded, not enforced: the author still gets told what's thin.
            $failures = $publisher->failures($lesson);

            $lesson->forceFill(['published_at' => now(), 'is_locked_by_default' => false])->save();
            $publishedNow[] = ['lesson_id' => $lesson->id, 'title' => $lesson->title];

            if ($failures !== []) {
                $incomplete[] = [
                    'lesson_id' => $lesson->id,
                    'title' => $lesson->title,
                    'reasons' => $failures,
                ];
            }
        }

        $course->update(['is_published' => true, 'status' => 'published']);

        $this->audit->record('course.published', $course, [], [
            'lessons_published' => count($publishedNow),
            'lessons_incomplete' => count($incomplete),
        ]);

        return (new CourseResource($course->load(['language', 'ownerUser'])->loadCount('levels')))
            ->additional(['meta' => [
                'lessons_published' => $publishedNow,
                'lessons_incomplete' => $incomplete,
            ]])
            ->response();
    }

    public function unpublish(Course $course): JsonResponse
    {
        $course->update(['is_published' => false, 'status' => 'draft']);

        return (new CourseResource($course->load(['language', 'ownerUser'])->loadCount('levels')))->response();
    }

    /**
     * Archive a course — pulled from the active catalogue without deleting it.
     * Archived courses are unpublished so learners no longer see them.
     */
    public function archive(Course $course): JsonResponse
    {
        $course->update(['status' => 'archived', 'is_published' => false]);

        return (new CourseResource($course->load(['language', 'ownerUser'])->loadCount('levels')))->response();
    }

    public function unarchive(Course $course): JsonResponse
    {
        $course->update(['status' => 'draft']);

        return (new CourseResource($course->load(['language', 'ownerUser'])->loadCount('levels')))->response();
    }

    public function levels(Course $course): AnonymousResourceCollection
    {
        return CourseLevelResource::collection($course->levels()->orderBy('position')->get());
    }
}
