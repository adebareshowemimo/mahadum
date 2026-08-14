<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ClassCourseAssignment;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Services\AuditLogger;
use App\Services\School\ClassCourseEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassCourseController extends Controller
{
    public function __construct(
        private ClassCourseEnrollmentService $enrollments,
        private AuditLogger $audit,
    ) {}

    /** Published course catalogue plus assignment state for this class. */
    public function index(SchoolClass $class): JsonResponse
    {
        $assigned = $class->courseAssignments()->pluck('course_id')->all();

        $courses = Course::query()
            ->where('is_published', true)
            ->with('language')
            ->orderBy('title')
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->description,
                'level_band' => $course->level_band,
                'language' => $course->language->code,
                'assigned' => in_array($course->id, $assigned, true),
            ]);

        return response()->json(['data' => $courses]);
    }

    /** Persistently assign a course to the class and enroll all current learners. */
    public function store(Request $request, SchoolClass $class, Course $course): JsonResponse
    {
        abort_unless($course->is_published, 422, 'Only published courses can be assigned.');

        [$assignment, $enrolled] = DB::transaction(function () use ($request, $class, $course) {
            $assignment = ClassCourseAssignment::firstOrCreate(
                ['school_class_id' => $class->id, 'course_id' => $course->id],
                ['assigned_by_user_id' => $request->user()->id],
            );

            $enrolled = $this->enrollments->syncCourse($class, $course);

            if ($assignment->wasRecentlyCreated) {
                $this->audit->record(
                    'class.course_assigned',
                    $assignment,
                    [],
                    ['class_id' => $class->id, 'course_id' => $course->id],
                    $class->organization_id,
                );
            }

            return [$assignment, $enrolled];
        });

        return response()->json(['data' => [
            'assignment_id' => $assignment->id,
            'course_id' => $course->id,
            'enrolled_count' => $enrolled,
        ]], $assignment->wasRecentlyCreated ? 201 : 200);
    }

    /** Stop assigning to future class members; existing learning records remain intact. */
    public function destroy(SchoolClass $class, Course $course): JsonResponse
    {
        $assignment = $class->courseAssignments()->where('course_id', $course->id)->firstOrFail();
        $assignment->delete();

        return response()->json(['data' => ['course_id' => $course->id, 'assigned' => false]]);
    }
}
