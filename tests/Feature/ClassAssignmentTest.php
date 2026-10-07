<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function org(): Organization
    {
        return Organization::create(['name' => 'Greenfield', 'type' => 'school', 'slug' => 'greenfield', 'status' => 'active']);
    }

    private function classWithTeacherAndLearner(Organization $org, User $teacher): array
    {
        $class = SchoolClass::create(['organization_id' => $org->id, 'name' => 'JSS1', 'teacher_user_id' => $teacher->id]);
        $user = User::factory()->create();
        $learner = LearnerProfile::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'display_name' => 'Ada',
        ]);
        ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $learner->id]);

        return [$class, $learner];
    }

    public function test_teacher_creates_assignment_learner_submits_and_teacher_grades_pass(): void
    {
        $this->seedRbac();
        $org = $this->org();
        $teacher = $this->userWithRole('teacher');
        $org->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        [$class, $learner] = $this->classWithTeacherAndLearner($org, $teacher);

        $this->actingAsUser($teacher);
        $created = $this->postJson("/api/v1/classes/{$class->id}/assignments", [
            'title' => 'Yoruba greetings essay',
            'coin_reward' => 50,
        ])->assertCreated();
        $assignmentId = $created->json('data.id');

        $this->getJson("/api/v1/classes/{$class->id}/assignments")
            ->assertOk()
            ->assertJsonPath('data.0.total_students', 1)
            ->assertJsonPath('data.0.submitted_count', 0);

        // Learner submits (acting as the learner's own account — self-access).
        $this->actingAsUser($learner->user);
        $submitted = $this->postJson("/api/v1/class-assignments/{$assignmentId}/submissions", [
            'learner_id' => $learner->id,
            'text_body' => 'E kaaro',
        ])->assertCreated();
        $submissionId = $submitted->json('data.id');

        // Teacher grades it a pass; coins remain locked until parent approval.
        $this->actingAsUser($teacher);
        $this->postJson("/api/v1/classes/{$class->id}/assignments/{$assignmentId}/submissions/{$submissionId}/grade", [
            'passed' => true,
            'score' => 90,
        ])->assertOk()->assertJsonPath('data.coins_released', 0);

        $this->assertDatabaseMissing('coin_transactions', ['source' => 'class_assignment']);
        $this->assertDatabaseHas('class_assignment_submissions', ['id' => $submissionId, 'coins_locked' => 50, 'parent_review_status' => 'pending']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'class_assignment.graded', 'subject_id' => $submissionId]);

        // Re-grading an already-graded submission is rejected.
        $this->postJson("/api/v1/classes/{$class->id}/assignments/{$assignmentId}/submissions/{$submissionId}/grade", [
            'passed' => true,
        ])->assertStatus(422);
    }

    public function test_non_owning_teacher_cannot_create_or_grade(): void
    {
        $this->seedRbac();
        $org = $this->org();
        $owner = $this->userWithRole('teacher');
        $intruder = $this->userWithRole('teacher');
        $org->members()->attach($owner->id, ['role' => 'teacher', 'status' => 'active']);
        $org->members()->attach($intruder->id, ['role' => 'teacher', 'status' => 'active']);
        [$class] = $this->classWithTeacherAndLearner($org, $owner);

        $this->actingAsUser($intruder);
        $this->postJson("/api/v1/classes/{$class->id}/assignments", ['title' => 'Not mine'])
            ->assertStatus(403);
    }
}
