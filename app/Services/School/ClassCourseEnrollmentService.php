<?php

namespace App\Services\School;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearnerProfile;
use App\Models\SchoolClass;
use App\Services\Learning\PathBuilder;
use App\Services\Learning\XapiRecorder;

class ClassCourseEnrollmentService
{
    public function __construct(
        private PathBuilder $paths,
        private XapiRecorder $xapi,
    ) {}

    /** Enroll one class learner in every course persistently assigned to the class. */
    public function syncLearner(SchoolClass $class, LearnerProfile $learner): int
    {
        $count = 0;

        foreach ($class->courseAssignments()->with('course')->get() as $assignment) {
            if ($assignment->course?->is_published && $this->enroll($learner, $assignment->course)) {
                $count++;
            }
        }

        return $count;
    }

    /** Assign a published course to every current learner in a class. */
    public function syncCourse(SchoolClass $class, Course $course): int
    {
        $count = 0;

        foreach ($class->enrollments()->with('learnerProfile')->get() as $classEnrollment) {
            if ($classEnrollment->learnerProfile && $this->enroll($classEnrollment->learnerProfile, $course)) {
                $count++;
            }
        }

        return $count;
    }

    private function enroll(LearnerProfile $learner, Course $course): bool
    {
        $enrollment = Enrollment::firstOrCreate(
            ['learner_profile_id' => $learner->id, 'course_id' => $course->id],
            ['status' => 'active', 'started_at' => now()],
        );

        $this->paths->build($enrollment);

        if ($enrollment->wasRecentlyCreated) {
            $this->xapi->record(
                $learner->id,
                XapiRecorder::VERB_REGISTERED,
                'courses',
                $course->id,
                $course->title,
                XapiRecorder::ACTIVITY_COURSE,
            );
        }

        return $enrollment->wasRecentlyCreated;
    }
}
