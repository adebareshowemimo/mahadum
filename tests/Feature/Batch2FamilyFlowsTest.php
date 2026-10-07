<?php

namespace Tests\Feature;

use App\Models\ClassAssignment;
use App\Models\ClassAssignmentSubmission;
use App\Models\ClassEnrollment;
use App\Models\CoinTransaction;
use App\Models\LessonProgress;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Services\Family\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class Batch2FamilyFlowsTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_chore_submit_more_reject_resubmit_approve_is_isolated_and_awards_once(): void
    {
        $this->seedRbac();
        Notification::fake();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $wallets = app(WalletService::class);
        $wallets->credit($wallets->walletFor($learner->family), 100, 'test_seed');
        $choreId = $this->postJson('/api/v1/chores', ['title' => 'Tidy', 'assignee_learner_profile_id' => $learner->id, 'coin_reward' => 15])->assertCreated()->json('data.id');
        $submit = "/api/v1/chores/$choreId/submissions";
        $review = "/api/v1/chores/$choreId/review";
        $this->getJson('/api/v1/reviews/pending')->assertJsonCount(0, 'data.chores');
        $this->postJson($review, ['decision' => 'approve'])->assertUnprocessable();
        $this->postJson($submit, ['learner_id' => $learner->id, 'completed' => true])->assertCreated();
        $this->postJson($submit, ['learner_id' => $learner->id, 'completed' => true])->assertUnprocessable();
        $this->getJson('/api/v1/reviews/pending')->assertJsonPath('data.chores.0.evidence_type', 'checkbox');
        foreach (['more_evidence', 'reject'] as $decision) {
            $this->postJson($review, ['decision' => $decision])->assertOk()->assertJsonPath('data.coins_released', 0);
            $this->assertDatabaseMissing('coin_transactions', ['source' => 'chore']);
            $this->getJson('/api/v1/reviews/pending')->assertJsonCount(0, 'data.chores');
            $this->postJson($review, ['decision' => 'approve'])->assertUnprocessable();
            $this->postJson($submit, ['learner_id' => $learner->id, 'completed' => true])->assertCreated();
        }
        $this->postJson($review, ['decision' => 'approve'])->assertOk()->assertJsonPath('data.coins_released', 15);
        $this->postJson($review, ['decision' => 'approve'])->assertUnprocessable();
        $this->postJson($submit, ['learner_id' => $learner->id, 'completed' => true])->assertUnprocessable();
        $this->assertDatabaseCount('chore_submissions', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'chore.reviewed', 'actor_user_id' => $parent->id]);
        $this->assertDatabaseHas('coin_transactions', ['source' => 'chore', 'amount' => 15, 'balance_after' => 15]);
        $this->assertSame(1, CoinTransaction::where('learner_profile_id', $learner->id)->where('source', 'chore')->count());
        $other = $this->userWithRole('parent');
        $otherChild = $this->parentWithChild($other);
        $this->actingAsUser($other);
        $this->postJson($submit, ['learner_id' => $otherChild->id, 'completed' => true])->assertForbidden();
        $this->postJson($review, ['decision' => 'approve'])->assertForbidden();
        $this->getJson("/api/v1/learners/$learner->id/tasks")->assertForbidden();
    }

    private function schoolFixture(): array
    {
        $this->seedRbac();
        Notification::fake();
        $org = Organization::create(['name' => 'Batch Two School', 'slug' => 'batch-two', 'type' => 'school', 'status' => 'active']);
        $teacher = $this->userWithRole('teacher');
        $org->members()->attach($teacher, ['role' => 'teacher', 'status' => 'active']);
        $class = SchoolClass::create(['organization_id' => $org->id, 'name' => 'Yoruba Class', 'teacher_user_id' => $teacher->id]);
        $parent = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        $learner->update(['organization_id' => $org->id]);
        ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $learner->id]);
        $this->actingAsUser($teacher);
        $id = $this->postJson("/api/v1/classes/$class->id/assignments", ['title' => 'Greetings', 'instructions' => 'Write a greeting', 'coin_reward' => 50])->assertCreated()->json('data.id');

        return [$class, ClassAssignment::findOrFail($id), $teacher, $parent, $learner];
    }

    public function test_learner_work_reaches_teacher_then_parent_queue_and_is_paid_only_once(): void
    {
        [$class, $assignment, $teacher, $parent, $learner] = $this->schoolFixture();
        $this->actingAsUser($parent);
        $this->getJson("/api/v1/learners/$learner->id/tasks")->assertOk()->assertJsonPath('data.assignments.0.title', 'Greetings');
        $id = $this->postJson("/api/v1/class-assignments/$assignment->id/submissions", ['learner_id' => $learner->id, 'text_body' => 'E kaaro'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/reviews/pending')->assertJsonCount(0, 'data.class_assignments');
        $parentReview = "/api/v1/class-assignment-submissions/$id/review";
        $this->postJson($parentReview, ['decision' => 'approve'])->assertUnprocessable();
        $this->actingAsUser($teacher);
        $this->getJson("/api/v1/classes/$class->id/assignments/$assignment->id")->assertJsonPath('data.roster.0.text_body', 'E kaaro');
        $grade = "/api/v1/classes/$class->id/assignments/$assignment->id/submissions/$id/grade";
        $this->postJson($grade, ['passed' => true, 'score' => 90, 'feedback' => 'Well done'])->assertOk()->assertJsonPath('data.coins_released', 0);
        $this->postJson($grade, ['passed' => true])->assertUnprocessable();
        $this->assertDatabaseMissing('coin_transactions', ['source' => 'class_assignment']);
        $this->postJson($parentReview, ['decision' => 'approve'])->assertForbidden();
        // Existing uploaded work remains visible to the parent; no recorder is added.
        $media = MediaAsset::create(['type' => 'audio', 'url' => 'assignments/existing-fixture.mp3']);
        ClassAssignmentSubmission::findOrFail($id)->update(['media_asset_id' => $media->id]);
        $this->actingAsUser($parent);
        $this->getJson('/api/v1/reviews/pending')->assertJsonPath('data.class_assignments.0.coin_reward', 50)->assertJsonPath('data.class_assignments.0.feedback', 'Well done')->assertJsonPath('data.class_assignments.0.media_type', 'audio')->assertJsonPath('data.class_assignments.0.media_url', Storage::disk('public')->url($media->url));
        $this->postJson("/api/v1/class-assignments/$assignment->id/submissions", ['learner_id' => $learner->id, 'text_body' => 'Replay'])->assertUnprocessable();
        $intruder = $this->userWithRole('parent');
        $this->parentWithChild($intruder);
        $this->actingAsUser($intruder);
        $this->postJson($parentReview, ['decision' => 'approve'])->assertForbidden();
        $this->getJson('/api/v1/reviews/pending')->assertJsonCount(0, 'data.class_assignments');
        $this->actingAsUser($parent);
        $this->postJson($parentReview, ['decision' => 'approve'])->assertOk()->assertJsonPath('data.coins_released', 50);
        $this->postJson($parentReview, ['decision' => 'approve'])->assertUnprocessable();
        $this->assertDatabaseHas('coin_transactions', ['source' => 'class_assignment', 'amount' => 50, 'balance_after' => 50]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'class_assignment.parent_reviewed', 'actor_user_id' => $parent->id]);
        $this->getJson('/api/v1/reviews/pending')->assertJsonCount(0, 'data.class_assignments');
    }

    public function test_declined_class_reward_cannot_be_approved_later_or_claimed_by_teacher(): void
    {
        [$class, $assignment, $teacher, $parent, $learner] = $this->schoolFixture();
        $this->actingAsUser($parent);
        $id = $this->postJson("/api/v1/class-assignments/$assignment->id/submissions", ['learner_id' => $learner->id, 'text_body' => 'Hello'])->assertCreated()->json('data.id');
        $this->actingAsUser($teacher);
        $this->postJson("/api/v1/classes/$class->id/assignments/$assignment->id/submissions/$id/grade", ['passed' => true])->assertOk();
        $this->actingAsUser($parent);
        $this->postJson("/api/v1/class-assignment-submissions/$id/review", ['decision' => 'reject'])->assertOk()->assertJsonPath('data.coins_released', 0);
        $this->postJson("/api/v1/class-assignment-submissions/$id/review", ['decision' => 'approve'])->assertUnprocessable();
        $this->assertDatabaseMissing('coin_transactions', ['source' => 'class_assignment']);
    }

    public function test_analytics_drilldown_requires_authorized_class_and_enrollment_and_reads_existing_activity(): void
    {
        [$class, $assignment, $teacher, , $learner] = $this->schoolFixture();
        $lesson = $this->publishedLesson();
        LessonProgress::create(['learner_profile_id' => $learner->id, 'lesson_id' => $lesson->id, 'status' => 'completed', 'score' => 80, 'completed_at' => now()]);
        QuizAttempt::create(['learner_profile_id' => $learner->id, 'quiz_id' => $lesson->components->firstWhere('type', 'quiz')->quiz->id, 'attempt_no' => 1, 'score' => 0.5, 'started_at' => now(), 'completed_at' => now()]);
        $this->getJson("/api/v1/classes/$class->id/analytics")->assertOk()->assertJsonPath('data.students.0.lessons_completed', 1);
        $url = "/api/v1/classes/$class->id/analytics/$learner->id";
        $this->getJson($url)->assertOk()->assertJsonPath('data.lessons.0.score', 80)->assertJsonPath('data.quizzes.0.score', 0.5)->assertJsonCount(0, 'data.speaking');
        $other = $this->parentWithChild($this->userWithRole('parent'));
        $other->update(['organization_id' => $class->organization_id]);
        $this->getJson("/api/v1/classes/$class->id/analytics/$other->id")->assertNotFound();
        $intruder = $this->userWithRole('teacher');
        $class->organization->members()->attach($intruder, ['role' => 'teacher', 'status' => 'active']);
        $this->actingAsUser($intruder);
        // Existing view policy grants same-school staff read access.
        $this->getJson($url)->assertOk();
        $this->getJson("/api/v1/learners/$learner->id/tasks")->assertForbidden();
        $foreignSchool = Organization::create(['name' => 'Foreign', 'slug' => 'foreign', 'type' => 'school', 'status' => 'active']);
        $foreignTeacher = $this->userWithRole('teacher');
        $foreignSchool->members()->attach($foreignTeacher, ['role' => 'teacher', 'status' => 'active']);
        $this->actingAsUser($foreignTeacher);
        $this->getJson($url)->assertForbidden();
    }
}
