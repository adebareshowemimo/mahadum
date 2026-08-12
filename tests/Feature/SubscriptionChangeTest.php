<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
    }

    public function test_parent_can_switch_from_one_plan_to_another(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $individual = Plan::where('code', 'premium_individual')->firstOrFail();
        $family = Plan::where('code', 'premium_family')->firstOrFail();

        $current = new Subscription([
            'plan_id' => $individual->id,
            'method' => 'invoice',
            'status' => 'active',
            'started_at' => now(),
            'renews_at' => now()->addMonth(),
        ]);
        $current->subscriber()->associate($parent);
        $current->save();

        $response = $this->postJson("/api/v1/subscriptions/{$current->id}/change", [
            'plan_id' => $family->id,
            'method' => 'invoice',
        ], ['Idempotency-Key' => 'change-1']);

        $response->assertCreated()->assertJsonPath('data.status', 'active');

        $this->assertSame('cancelled', $current->fresh()->status);

        $newSubscription = Subscription::where('plan_id', $family->id)
            ->where('subscriber_id', $parent->id)
            ->firstOrFail();
        $this->assertSame('active', $newSubscription->status);
    }

    public function test_cannot_switch_to_the_same_plan(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $plan = Plan::where('code', 'premium_individual')->firstOrFail();

        $current = new Subscription([
            'plan_id' => $plan->id,
            'method' => 'invoice',
            'status' => 'active',
            'started_at' => now(),
            'renews_at' => now()->addMonth(),
        ]);
        $current->subscriber()->associate($parent);
        $current->save();

        $this->postJson("/api/v1/subscriptions/{$current->id}/change", [
            'plan_id' => $plan->id,
            'method' => 'invoice',
        ], ['Idempotency-Key' => 'change-2'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'same_plan');

        $this->assertSame('active', $current->fresh()->status);
    }

    public function test_cannot_change_someone_elses_subscription(): void
    {
        $owner = $this->userWithRole('parent');
        $plan = Plan::where('code', 'premium_individual')->firstOrFail();
        $otherPlan = Plan::where('code', 'premium_family')->firstOrFail();

        $current = new Subscription([
            'plan_id' => $plan->id,
            'method' => 'invoice',
            'status' => 'active',
            'started_at' => now(),
            'renews_at' => now()->addMonth(),
        ]);
        $current->subscriber()->associate($owner);
        $current->save();

        $this->actingAsUser($this->userWithRole('parent'));

        $this->postJson("/api/v1/subscriptions/{$current->id}/change", [
            'plan_id' => $otherPlan->id,
            'method' => 'invoice',
        ], ['Idempotency-Key' => 'change-3'])->assertForbidden();
    }
}
