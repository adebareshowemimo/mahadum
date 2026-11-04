<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\QuestionResponse;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\School\LinkedClassAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class ClassQuizAssignmentTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_assigned_course_quiz_creates_an_automatically_scored_class_result(): void
    {
        $this->seedRbac();
        $lesson = $this->publishedLesson();
        $course = $lesson->courseLevel->course;
        $school = Organization::create(['name' => 'Quiz School', 'slug' => 'quiz-school', 'type' => 'school', 'status' => 'active']);
        $teacher = $this->userWithRole('teacher');
        $school->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Class A', 'teacher_user_id' => $teacher->id]);
        $class->courseAssignments()->create(['course_id' => $course->id, 'assigned_by_user_id' => $teacher->id]);
        $user = User::factory()->create();
        $learner = LearnerProfile::create(['organization_id' => $school->id, 'user_id' => $user->id, 'display_name' => 'Ada']);
        ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $learner->id]);
        $this->actingAsUser($teacher);

        $this->getJson("/api/v1/classes/{$class->id}/assignment-lessons")
            ->assertOk()->assertJsonPath('data.0.id', $lesson->id);
        $created = $this->postJson("/api/v1/classes/{$class->id}/assignments", [
            'title' => 'Lesson quiz', 'linked_lesson_id' => $lesson->id, 'coin_reward' => 10,
        ])->assertCreated();
        $assignmentId = $created->json('data.id');
        $quiz = $lesson->components->firstWhere('type', 'quiz')->quiz;
        $question = $quiz->questions->first();
        $correct = $question->options->firstWhere('is_correct', true);
        $attempt = QuizAttempt::create(['learner_profile_id' => $learner->id, 'quiz_id' => $quiz->id,
            'attempt_no' => 1, 'score' => 1, 'passed' => true, 'started_at' => now(), 'completed_at' => now()]);
        QuestionResponse::create(['learner_profile_id' => $learner->id, 'question_id' => $question->id,
            'quiz_attempt_id' => $attempt->id, 'given_answer' => ['option_id' => $correct->id],
            'is_correct' => true, 'answered_at' => now()]);

        app(LinkedClassAssignmentService::class)->record($learner, $lesson);
        app(LinkedClassAssignmentService::class)->record($learner, $lesson);
        $this->assertDatabaseCount('class_assignment_submissions', 1);
        $this->assertDatabaseHas('class_assignment_submissions', ['class_assignment_id' => $assignmentId,
            'learner_profile_id' => $learner->id, 'status' => 'graded', 'score' => 100,
            'coins_locked' => 10, 'parent_review_status' => 'pending']);
        $this->getJson("/api/v1/classes/{$class->id}/quizzes")
            ->assertOk()->assertJsonPath('data.0.average_score', 100);
        $this->getJson("/api/v1/classes/{$class->id}/quiz-attempts/{$attempt->id}")
            ->assertOk()->assertJsonPath('data.questions.0.given_answer', $correct->label)
            ->assertJsonPath('data.questions.0.is_correct', true);
        $outsider = LearnerProfile::create(['organization_id' => $school->id, 'display_name' => 'Not in class']);
        $privateAttempt = QuizAttempt::create(['learner_profile_id' => $outsider->id, 'quiz_id' => $quiz->id,
            'attempt_no' => 1, 'score' => 0, 'started_at' => now(), 'completed_at' => now()]);
        $this->getJson("/api/v1/classes/{$class->id}/quiz-attempts/{$privateAttempt->id}")->assertNotFound();
    }
}
