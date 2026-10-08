<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Services\Family\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class AssignmentFlowTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function assignmentComponent(Lesson $lesson, int $coinReward = 50)
    {
        $component = $lesson->components()->create(['type' => 'assignment', 'position' => 4, 'xp_value' => 6, 'is_required' => false]);
        $component->assignment()->create(['prompt' => 'Record a greeting', 'expected_media' => 'video', 'coin_reward' => $coinReward]);

        return $component;
    }

    public function test_submit_escrows_coins_and_completes_the_component(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $component = $this->assignmentComponent($lesson);

        $this->postJson('/api/v1/assignment-submissions', [
            'learner_id' => $learner->id, 'component_id' => $component->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.coins_pending', 50);

        // Escrowed, not yet released.
        $this->assertDatabaseHas('assignment_submissions', [
            'learner_profile_id' => $learner->id, 'parent_review_status' => 'pending', 'coins_locked' => 50,
        ]);
        $this->assertDatabaseMissing('coin_transactions', ['source' => 'assignment']);
        // Component is complete so the lesson can still be finished (learning isn't gated on approval).
        $this->assertDatabaseHas('component_progress', ['lesson_component_id' => $component->id, 'status' => 'complete']);
    }

    public function test_parent_approval_releases_coins_to_the_learner(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $wallets = app(WalletService::class);
        $wallets->credit($wallets->walletFor($learner->family), 50, 'test_seed');
        $lesson = $this->publishedLesson();
        $component = $this->assignmentComponent($lesson);

        $id = $this->postJson('/api/v1/assignment-submissions', [
            'learner_id' => $learner->id, 'component_id' => $component->id,
        ])->json('data.id');

        $this->postJson("/api/v1/assignment-submissions/{$id}/review", ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.coins_released', 50);

        $this->assertDatabaseHas('coin_transactions', [
            'source' => 'assignment', 'type' => 'credit', 'amount' => 50, 'learner_profile_id' => $learner->id,
        ]);
        $this->assertDatabaseHas('assignment_submissions', ['id' => $id, 'parent_review_status' => 'approved', 'decided_by' => $parent->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'assignment.reviewed']);
        $this->assertSame(0, $wallets->walletFor($learner->family)->coin_balance);

        // Idempotent: a second review is rejected.
        $this->postJson("/api/v1/assignment-submissions/{$id}/review", ['decision' => 'approve'])->assertStatus(422);
    }

    public function test_unfunded_assignment_approval_stays_pending_and_can_be_retried_after_funding(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $component = $this->assignmentComponent($this->publishedLesson());
        $id = $this->postJson('/api/v1/assignment-submissions', ['learner_id' => $learner->id, 'component_id' => $component->id])->json('data.id');
        $this->postJson("/api/v1/assignment-submissions/{$id}/review", ['decision' => 'approve'])->assertUnprocessable();
        $this->assertDatabaseHas('assignment_submissions', ['id' => $id, 'parent_review_status' => 'pending', 'coins_locked' => 50]);
        $this->assertDatabaseMissing('coin_transactions', ['source' => 'assignment']);
        $wallets = app(WalletService::class);
        $wallets->credit($wallets->walletFor($learner->family), 50, 'test_seed');
        $this->postJson("/api/v1/assignment-submissions/{$id}/review", ['decision' => 'approve'])->assertOk();
        $this->assertSame(0, $wallets->walletFor($learner->family)->coin_balance);
        $this->assertSame(50, $wallets->walletFor($learner)->coin_balance);
    }

    public function test_rejection_releases_no_coins(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $component = $this->assignmentComponent($lesson);

        $id = $this->postJson('/api/v1/assignment-submissions', [
            'learner_id' => $learner->id, 'component_id' => $component->id,
        ])->json('data.id');

        $this->postJson("/api/v1/assignment-submissions/{$id}/review", ['decision' => 'reject'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.coins_released', 0);

        $this->assertDatabaseMissing('coin_transactions', ['source' => 'assignment']);
    }

    public function test_a_parent_cannot_review_another_familys_assignment(): void
    {
        $this->seedRbac();
        $owner = $this->userWithRole('parent');
        $learner = $this->parentWithChild($owner);
        $lesson = $this->publishedLesson();
        $component = $this->assignmentComponent($lesson);

        $this->actingAsUser($owner);
        $id = $this->postJson('/api/v1/assignment-submissions', [
            'learner_id' => $learner->id, 'component_id' => $component->id,
        ])->json('data.id');

        // A different parent with their own family may not review it (SoD).
        $intruder = $this->userWithRole('parent');
        $this->parentWithChild($intruder);
        $this->actingAsUser($intruder);
        $this->postJson("/api/v1/assignment-submissions/{$id}/review", ['decision' => 'approve'])->assertStatus(403);
    }
}
