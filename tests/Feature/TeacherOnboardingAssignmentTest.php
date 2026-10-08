<?php

namespace Tests\Feature;

use App\Models\ClassAssignmentSubmission;
use App\Models\ClassEnrollment;
use App\Models\CoinTransaction;
use App\Models\Family;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Notifications\TeacherInvited;
use App\Services\Family\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class TeacherOnboardingAssignmentTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_csv_invitation_through_parent_funded_assignment_reward(): void
    {
        $this->seedRbac();
        Notification::fake();
        $school = Organization::create(['name' => 'Flow School', 'slug' => 'flow-school', 'type' => 'school', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $school->members()->attach($admin, ['role' => 'school_admin', 'status' => 'active']);
        $teacher = $this->userWithRole('parent', ['email' => 'teacher-flow@example.com']);
        $existingFamily = Family::create(['name' => 'Teacher household', 'owner_user_id' => $teacher->id]);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Yoruba class']);
        $parent = $this->userWithRole('parent');
        $learner = $this->parentWithChild($parent);
        $learner->update(['organization_id' => $school->id]);
        ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $learner->id]);

        $this->actingAsUser($admin->fresh());
        $csv = "Firstname,Lastname,Email,Phone\nTest,Teacher,teacher-flow@example.com,+2348031234567\n";
        $this->post("/api/v1/schools/{$school->id}/teachers/import", [
            'file' => UploadedFile::fake()->createWithContent('teachers.csv', $csv), 'preview' => '0',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.created', 1);
        $invitation = Notification::sent(new AnonymousNotifiable, TeacherInvited::class)->sole();
        $token = basename($invitation->toMail((object) [])->actionUrl);
        $this->actingAsUser($teacher->fresh());
        $this->postJson("/api/v1/teacher-invitations/{$token}/accept")->assertOk();
        $this->postJson("/api/v1/teacher-invitations/{$token}/accept")->assertOk();
        $this->assertTrue($teacher->fresh()->hasAllRoles(['parent', 'teacher']));
        $this->assertSame($existingFamily->id, $teacher->ownedFamilies()->sole()->id);

        $this->actingAsUser($admin->fresh());
        $this->putJson("/api/v1/classes/{$class->id}", ['teacher_user_id' => $teacher->id])->assertOk();
        $this->actingAsUser($teacher->fresh());
        $this->getJson('/api/v1/classes?mine=1')->assertOk()->assertJsonPath('data.0.id', $class->id);
        $assignment = $this->postJson("/api/v1/classes/{$class->id}/assignments", [
            'title' => 'Written greetings', 'coin_reward' => 50,
        ])->assertCreated()->json('data.id');

        $this->actingAsUser($parent->fresh());
        $this->getJson("/api/v1/learners/{$learner->id}/tasks")->assertOk()->assertJsonPath('data.assignments.0.title', 'Written greetings');
        $submission = $this->postJson("/api/v1/class-assignments/{$assignment}/submissions", [
            'learner_id' => $learner->id, 'text_body' => 'E kaaro',
        ])->assertCreated()->json('data.id');
        // Existing uploaded evidence is supported; this does not add recording.
        $asset = MediaAsset::create(['type' => 'audio', 'url' => 'assignments/existing-evidence.mp3']);
        ClassAssignmentSubmission::findOrFail($submission)->update(['media_asset_id' => $asset->id]);
        $review = "/api/v1/class-assignment-submissions/{$submission}/review";
        $this->postJson($review, ['decision' => 'approve'])->assertUnprocessable();

        $this->actingAsUser($teacher->fresh());
        $this->getJson("/api/v1/classes/{$class->id}/assignments/{$assignment}")->assertOk()
            ->assertJsonPath('data.can_grade', true)->assertJsonPath('data.roster.0.media_type', 'audio')
            ->assertJsonPath('data.roster.0.media_url', Storage::disk('public')->url($asset->url));
        $this->postJson("/api/v1/classes/{$class->id}/assignments/{$assignment}/submissions/{$submission}/grade", [
            'passed' => true, 'feedback' => 'Well done',
        ])->assertOk()->assertJsonPath('data.coins_released', 0);
        $this->postJson($review, ['decision' => 'approve'])->assertForbidden();

        $this->actingAsUser($parent->fresh());
        $this->getJson('/api/v1/reviews/pending')->assertOk()->assertJsonPath('data.class_assignments.0.id', $submission);
        $this->postJson($review, ['decision' => 'approve'])->assertUnprocessable();
        $this->assertDatabaseHas('class_assignment_submissions', ['id' => $submission, 'parent_review_status' => 'pending']);
        $wallets = app(WalletService::class);
        $funding = $wallets->walletFor($learner->family);
        $wallets->credit($funding, 100, 'test_seed');
        $this->postJson($review, ['decision' => 'approve'])->assertOk()->assertJsonPath('data.coins_released', 50);
        $this->postJson($review, ['decision' => 'approve'])->assertUnprocessable();
        $this->assertSame(50, $funding->fresh()->coin_balance);
        $this->assertSame(50, $wallets->walletFor($learner)->coin_balance);
        $this->assertSame(2, CoinTransaction::where('source', 'class_assignment')->count());
        $this->getJson('/api/v1/reviews/pending')->assertOk()->assertJsonCount(0, 'data.class_assignments');
    }

    public function test_removed_or_changed_teacher_membership_cannot_grade_existing_work(): void
    {
        $this->seedRbac();
        $school = Organization::create(['name' => 'Membership School', 'slug' => 'membership-school', 'type' => 'school', 'status' => 'active']);
        $teacher = $this->userWithRole('teacher');
        $school->members()->attach($teacher, ['role' => 'teacher', 'status' => 'active']);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Existing class', 'teacher_user_id' => $teacher->id]);
        $this->actingAsUser($teacher->fresh());
        $assignment = $this->postJson("/api/v1/classes/{$class->id}/assignments", ['title' => 'Existing work', 'coin_reward' => 10])->assertCreated()->json('data.id');
        $learner = $this->parentWithChild($this->userWithRole('parent'));
        $submission = ClassAssignmentSubmission::create(['class_assignment_id' => $assignment, 'learner_profile_id' => $learner->id, 'status' => 'submitted']);
        $grade = "/api/v1/classes/{$class->id}/assignments/{$assignment}/submissions/{$submission->id}/grade";

        $school->members()->updateExistingPivot($teacher->id, ['role' => 'supervisor']);
        $this->getJson("/api/v1/classes/{$class->id}/assignments/{$assignment}")->assertOk()->assertJsonPath('data.can_grade', false);
        $this->postJson($grade, ['passed' => true])->assertForbidden();
        $this->postJson("/api/v1/classes/{$class->id}/assignments", ['title' => 'Unauthorized work'])->assertForbidden();
        $school->members()->updateExistingPivot($teacher->id, ['role' => 'teacher', 'status' => 'inactive']);
        $this->getJson("/api/v1/classes/{$class->id}/assignments/{$assignment}")->assertForbidden();
        $this->postJson($grade, ['passed' => true])->assertForbidden();
        $school->members()->detach($teacher->id);
        $this->postJson($grade, ['passed' => true])->assertForbidden();
        $this->assertDatabaseHas('class_assignment_submissions', ['id' => $submission->id, 'status' => 'submitted', 'coins_locked' => 0]);
        $this->assertDatabaseMissing('coin_transactions', ['source' => 'class_assignment']);
    }
}
