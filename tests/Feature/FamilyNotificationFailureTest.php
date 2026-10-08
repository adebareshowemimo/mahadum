<?php

namespace Tests\Feature;

use App\Models\ClassAssignmentSubmission;
use App\Models\ClassEnrollment;
use App\Models\FamilyAlertPreference;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Notifications\FamilyActivityAlert;
use App\Services\Family\FamilyAlertService;
use App\Services\Family\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class FamilyNotificationFailureTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function unavailableAlerts(): void
    {
        $this->mock(FamilyAlertService::class)->shouldReceive('forLearner')->once()->andThrow(new \RuntimeException('Simulated queue failure'));
    }

    public function test_chore_submission_and_funded_approval_stay_successful_when_notifications_fail(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent')->fresh());
        $child = $this->parentWithChild($parent);
        $child->update(['user_id' => $this->userWithRole('student')->id]);
        $wallets = app(WalletService::class);
        $funding = $wallets->walletFor($child->family);
        $wallets->credit($funding, 100, 'test_seed');
        $id = $this->postJson('/api/v1/chores', ['title' => 'Read', 'coin_reward' => 20, 'assignee_learner_profile_id' => $child->id])->assertCreated()->json('data.id');
        $this->unavailableAlerts();
        $this->postJson("/api/v1/chores/{$id}/submissions", ['learner_id' => $child->id, 'completed' => true])
            ->assertCreated()->assertJsonPath('data.status', 'pending_review');
        $this->assertDatabaseCount('chore_submissions', 1);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Simulated dispatch failure'));
        $this->postJson("/api/v1/chores/{$id}/review", ['decision' => 'approve'])->assertOk()->assertJsonPath('data.coins_released', 20);
        $this->postJson("/api/v1/chores/{$id}/review", ['decision' => 'approve'])->assertUnprocessable();
        $this->assertSame(80, $funding->fresh()->coin_balance);
        $this->assertSame(20, $wallets->walletFor($child)->coin_balance);
        $this->assertDatabaseHas('chores', ['id' => $id, 'status' => 'approved']);
    }

    public function test_existing_assignment_submission_and_approval_do_not_fail_after_commit(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent')->fresh());
        $child = $this->parentWithChild($parent);
        $child->update(['user_id' => $this->userWithRole('student')->id]);
        $lesson = $this->publishedLesson();
        $component = $lesson->components()->create(['type' => 'assignment', 'position' => 4, 'xp_value' => 6, 'is_required' => false]);
        $component->assignment()->create(['prompt' => 'Existing work', 'expected_media' => 'audio', 'coin_reward' => 10]);
        $this->unavailableAlerts();
        $id = $this->postJson('/api/v1/assignment-submissions', ['learner_id' => $child->id, 'component_id' => $component->id])
            ->assertCreated()->assertJsonPath('data.coins_pending', 10)->json('data.id');
        $wallets = app(WalletService::class);
        $funding = $wallets->walletFor($child->family);
        $wallets->credit($funding, 100, 'test_seed');
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Simulated dispatch failure'));
        $this->postJson("/api/v1/assignment-submissions/{$id}/review", ['decision' => 'approve'])->assertOk()->assertJsonPath('data.coins_released', 10);
        $this->postJson("/api/v1/assignment-submissions/{$id}/review", ['decision' => 'approve'])->assertUnprocessable();
        $this->assertSame(90, $funding->fresh()->coin_balance);
        $this->assertSame(10, $wallets->walletFor($child)->coin_balance);
        $this->assertDatabaseHas('assignment_submissions', ['id' => $id, 'parent_review_status' => 'approved']);
    }

    public function test_teacher_grade_remains_saved_when_both_student_and_family_dispatch_fail(): void
    {
        $this->seedRbac();
        $school = Organization::create(['name' => 'Queue School', 'slug' => 'queue-school', 'type' => 'school', 'status' => 'active']);
        $teacher = $this->userWithRole('teacher')->fresh();
        $school->members()->attach($teacher, ['role' => 'teacher', 'status' => 'active']);
        $class = SchoolClass::create(['organization_id' => $school->id, 'name' => 'Class', 'teacher_user_id' => $teacher->id]);
        $child = $this->parentWithChild($this->userWithRole('parent'));
        $child->update(['organization_id' => $school->id, 'user_id' => $this->userWithRole('student')->id]);
        ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $child->id]);
        $this->actingAsUser($teacher);
        $id = $this->postJson("/api/v1/classes/{$class->id}/assignments", ['title' => 'Work', 'coin_reward' => 20])->assertCreated()->json('data.id');
        $submission = ClassAssignmentSubmission::create(['class_assignment_id' => $id, 'learner_profile_id' => $child->id, 'status' => 'submitted']);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Simulated dispatch failure'));
        $this->unavailableAlerts();
        $url = "/api/v1/classes/{$class->id}/assignments/{$id}/submissions/{$submission->id}/grade";
        $this->postJson($url, ['passed' => true])->assertOk()->assertJsonPath('data.coins_released', 0);
        $this->postJson($url, ['passed' => true])->assertUnprocessable();
        $this->assertDatabaseHas('class_assignment_submissions', ['id' => $submission->id, 'status' => 'graded', 'parent_review_status' => 'pending', 'coins_locked' => 20]);
        $this->assertDatabaseCount('coin_transactions', 0);
    }

    public function test_scheduled_alerts_continue_after_one_family_fails_and_retry_only_its_episode(): void
    {
        $this->seedRbac();
        $first = $this->userWithRole('parent')->fresh();
        $second = $this->userWithRole('parent')->fresh();
        $firstChild = $this->parentWithChild($first);
        $secondChild = $this->parentWithChild($second);
        foreach ([$firstChild, $secondChild] as $child) {
            FamilyAlertPreference::create(['family_id' => $child->family_id, 'low_balance_coins' => 0]);
        }
        Notification::shouldReceive('send')->once()->with(Mockery::on(fn ($user) => $user->id === $first->id), Mockery::type(FamilyActivityAlert::class))
            ->andThrow(new \RuntimeException('Simulated first-family dispatch failure'));
        Notification::shouldReceive('send')->once()->with(Mockery::on(fn ($user) => $user->id === $second->id), Mockery::type(FamilyActivityAlert::class))->andReturnNull();
        $this->artisan('family:send-alerts')->expectsOutput('Evaluated 2 families; 1 failed.')->assertFailed();
        $this->assertNull(DB::table('family_alert_states')->where('family_id', $firstChild->family_id)->where('key', 'low_balance')->value('episode'));
        $this->assertSame('threshold:0', DB::table('family_alert_states')->where('family_id', $secondChild->family_id)->where('key', 'low_balance')->value('episode'));
        Notification::fake();
        $this->artisan('family:send-alerts')->expectsOutput('Evaluated 2 families; 0 failed.')->assertSuccessful();
        Notification::assertSentToTimes($first, FamilyActivityAlert::class, 1);
        Notification::assertNotSentTo($second, FamilyActivityAlert::class);
        $this->artisan('family:send-alerts')->assertSuccessful();
        Notification::assertSentToTimes($first, FamilyActivityAlert::class, 1);
    }
}
