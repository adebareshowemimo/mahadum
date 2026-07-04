<?php

namespace App\Http\Controllers\Concerns;

use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Gate;

trait ResolvesLearner
{
    /**
     * Load a learner referenced in the request body and authorize the caller
     * against LearnerProfilePolicy::view (parent/self/same-tenant staff).
     */
    protected function learner(int $id): LearnerProfile
    {
        $learner = LearnerProfile::findOrFail($id);
        Gate::authorize('view', $learner);

        return $learner;
    }

    protected function lessonProgress(LearnerProfile $learner, Lesson $lesson): LessonProgress
    {
        return LessonProgress::firstOrCreate(
            ['learner_profile_id' => $learner->id, 'lesson_id' => $lesson->id],
            ['status' => 'in_progress', 'started_at' => now()],
        );
    }
}
