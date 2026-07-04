<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreEnrollmentRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Learning\PathBuilder;
use App\Services\Learning\XapiRecorder;
use Illuminate\Http\JsonResponse;

class EnrollmentController extends Controller
{
    use ResolvesLearner;

    public function store(StoreEnrollmentRequest $request, PathBuilder $pathBuilder, XapiRecorder $xapi): JsonResponse
    {
        $learner = $this->learner($request->integer('learner_id'));
        $course = Course::findOrFail($request->integer('course_id'));

        if (! $course->is_published) {
            return response()->json([
                'error' => ['code' => 'course_not_published', 'message' => 'This course is not available yet.', 'status' => 422],
            ], 422);
        }

        $wasNew = ! Enrollment::where('learner_profile_id', $learner->id)->where('course_id', $course->id)->exists();

        $enrollment = Enrollment::firstOrCreate(
            ['learner_profile_id' => $learner->id, 'course_id' => $course->id],
            ['status' => 'active', 'started_at' => now()],
        );

        if ($wasNew) {
            $xapi->record($learner->id, XapiRecorder::VERB_REGISTERED, 'courses', $course->id, $course->title, XapiRecorder::ACTIVITY_COURSE);
        }

        $nodes = $pathBuilder->build($enrollment);

        return response()->json(['data' => [
            'enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'path' => $nodes->map(fn ($n) => [
                'lesson_id' => $n->lesson_id,
                'state' => $n->state,
                'position' => $n->position,
            ])->values(),
        ]], 201);
    }
}
