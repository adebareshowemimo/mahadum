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
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
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

        // Admin oversight filters (only meaningful for CMS roles that see drafts).
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('title', 'like', "%{$q}%");
        }

        return CourseResource::collection($query->latest()->paginate($perPage));
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
     * Publish a course *and* its draft lessons in one action, so an author
     * doesn't have to walk the tree publishing each lesson first.
     *
     * A draft lesson is only skipped when it would be broken for learners —
     * the Content Model §6 checks in LessonPublishService (no video/quiz
     * component, a video still transcoding, a quiz question with no correct
     * answer). Those lessons are reported back by name so the author can fix
     * them; the course still goes live on whatever did publish. If nothing in
     * the course can publish, the course stays draft and the response carries
     * the per-lesson reasons instead of a generic "needs a published lesson".
     */
    public function publish(Course $course, LessonPublishService $publisher): JsonResponse
    {
        $lessons = Lesson::whereHas('courseLevel', fn ($q) => $q->where('course_id', $course->id))
            ->with(['components.video', 'components.quiz.questions.options'])
            ->get();

        // Counted before the cascade — the loop mutates these models in place.
        $alreadyLive = $lessons->whereNotNull('published_at')->count();

        $publishedNow = [];
        $blocked = [];

        foreach ($lessons->whereNull('published_at') as $lesson) {
            $failures = $publisher->failures($lesson);

            if ($failures !== []) {
                $blocked[] = [
                    'lesson_id' => $lesson->id,
                    'title' => $lesson->title,
                    'reasons' => $failures,
                ];

                continue;
            }

            $lesson->forceFill(['published_at' => now(), 'is_locked_by_default' => false])->save();
            $publishedNow[] = ['lesson_id' => $lesson->id, 'title' => $lesson->title];
        }

        if ($alreadyLive === 0 && $publishedNow === []) {
            return response()->json([
                'error' => [
                    'code' => 'not_publishable',
                    'message' => $blocked === []
                        ? 'This course has no lessons yet, so there is nothing to publish.'
                        : 'None of this course’s lessons meet the publish requirements yet.',
                    'status' => 422,
                    'details' => $blocked,
                ],
            ], 422);
        }

        $course->update(['is_published' => true, 'status' => 'published']);

        $this->audit->record('course.published', $course, [], [
            'lessons_published' => count($publishedNow),
            'lessons_blocked' => count($blocked),
        ]);

        return (new CourseResource($course->load(['language', 'ownerUser'])->loadCount('levels')))
            ->additional(['meta' => [
                'lessons_published' => $publishedNow,
                'lessons_blocked' => $blocked,
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
