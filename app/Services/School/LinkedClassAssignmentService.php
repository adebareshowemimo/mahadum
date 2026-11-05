<?php

namespace App\Services\School;

use App\Models\ClassAssignment;
use App\Models\ClassAssignmentSubmission;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\QuizAttempt;

class LinkedClassAssignmentService
{
    public function record(LearnerProfile $learner, Lesson $lesson): void
    {
        $quizIds = $lesson->components()->where('type', 'quiz')->with('quiz')->get()
            ->pluck('quiz.id')->filter()->values();
        if ($quizIds->isEmpty()) {
            return;
        }
        $attempts = QuizAttempt::where('learner_profile_id', $learner->id)
            ->whereIn('quiz_id', $quizIds)->whereNotNull('completed_at')
            ->orderByDesc('id')->get()->unique('quiz_id');
        if ($attempts->count() !== $quizIds->count()) {
            return;
        }
        $score = (int) round($attempts->avg('score') * 100);
        $passed = $attempts->every(fn (QuizAttempt $attempt) => $attempt->passed);

        ClassAssignment::withoutTenancy()->where('linked_lesson_id', $lesson->id)
            ->where('organization_id', $learner->organization_id)
            ->with('schoolClass')->get()->each(function (ClassAssignment $assignment) use ($learner, $score, $passed) {
                if (! $assignment->schoolClass->currentEnrollments()
                    ->where('learner_profile_id', $learner->id)->exists()) {
                    return;
                }
                ClassAssignmentSubmission::firstOrCreate(
                    ['class_assignment_id' => $assignment->id, 'learner_profile_id' => $learner->id],
                    ['status' => 'graded', 'passed' => $passed, 'score' => $score,
                        'submitted_at' => now(), 'graded_at' => now(),
                        'coins_locked' => $passed ? $assignment->coin_reward : 0,
                        'parent_review_status' => $passed && $assignment->coin_reward > 0 ? 'pending' : 'not_required'],
                );
            });
    }
}
