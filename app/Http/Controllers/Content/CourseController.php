<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreCourseRequest;
use App\Http\Requests\Content\UpdateCourseRequest;
use App\Http\Resources\CourseLevelResource;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Course::query()->with(['language', 'ownerUser'])->withCount('levels');

        // Learners only see published courses; CMS roles see drafts too.
        if (! $request->user()->can('content.courses.view')) {
            $query->where('is_published', true);
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

        return CourseResource::collection($query->latest()->paginate(20));
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

        return (new CourseResource($course->load('language')))
            ->response()->setStatusCode(201);
    }

    public function update(UpdateCourseRequest $request, Course $course): CourseResource
    {
        $course->update($request->validated());

        return new CourseResource($course->load('language'));
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json(null, 204);
    }

    /**
     * Publish a course (make it visible to learners). Publish-rule: the course
     * must contain at least one published lesson, else there is nothing to learn.
     */
    public function publish(Course $course): JsonResponse
    {
        $hasPublishedLesson = Lesson::whereNotNull('published_at')
            ->whereHas('courseLevel', fn ($q) => $q->where('course_id', $course->id))
            ->exists();

        if (! $hasPublishedLesson) {
            return response()->json([
                'error' => [
                    'code' => 'not_publishable',
                    'message' => 'A course needs at least one published lesson before it can be published.',
                ],
            ], 422);
        }

        $course->update(['is_published' => true, 'status' => 'published']);

        return (new CourseResource($course->load(['language', 'ownerUser'])->loadCount('levels')))->response();
    }

    public function unpublish(Course $course): JsonResponse
    {
        $course->update(['is_published' => false, 'status' => 'draft']);

        return (new CourseResource($course->load(['language', 'ownerUser'])->loadCount('levels')))->response();
    }

    public function levels(Course $course): AnonymousResourceCollection
    {
        return CourseLevelResource::collection($course->levels()->orderBy('position')->get());
    }
}
