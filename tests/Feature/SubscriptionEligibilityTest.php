<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\LearnerProfile;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\EntitlementResolver;
use App\Services\Billing\PaymentService;
use App\Services\Learning\LessonAccess;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
    }

    public function test_family_plan_requires_family_account_and_individual_plan_rejects_two_children(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $familyPlan = Plan::where('code', 'premium_family')->firstOrFail();
        $individual = Plan::where('code', 'premium_individual')->firstOrFail();

        $this->postJson('/api/v1/subscriptions', ['plan_id' => $familyPlan->id, 'method' => 'card'], ['Idempotency-Key' => 'family-only'])
            ->assertStatus(422)->assertJsonPath('error.code', 'plan_ineligible');

        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Home']);
        LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'One']);
        LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Two']);

        $this->postJson('/api/v1/subscriptions', ['plan_id' => $individual->id, 'method' => 'card'], ['Idempotency-Key' => 'too-many'])
            ->assertStatus(422)->assertJsonPath('error.code', 'plan_ineligible');
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_legacy_individual_subscription_covers_only_one_child_and_pending_is_not_history(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Home']);
        $first = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'One']);
        $second = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Two']);
        $plan = Plan::where('code', 'premium_individual')->firstOrFail();
        $active = new Subscription(['plan_id' => $plan->id, 'method' => 'card', 'status' => 'active', 'started_at' => now()]);
        $active->subscriber()->associate($parent);
        $active->save();
        $pending = new Subscription(['plan_id' => $plan->id, 'method' => 'card', 'status' => 'pending']);
        $pending->subscriber()->associate($parent);
        $pending->save();

        $this->assertTrue(app(EntitlementResolver::class)->forLearner($first)['unlimited_hearts']);
        $this->assertFalse(app(EntitlementResolver::class)->forLearner($second)['unlimited_hearts']);
        $this->assertSame('paid', app(LessonAccess::class)->tier($first));
        $this->assertSame('free', app(LessonAccess::class)->tier($second));
        $this->getJson('/api/v1/subscriptions')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_confirmed_renewal_over_profile_limit_is_held_for_review(): void
    {
        $parent = $this->userWithRole('parent');
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Home']);
        LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'One']);
        LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Two']);
        $plan = Plan::where('code', 'premium_individual')->firstOrFail();
        $sub = new Subscription(['plan_id' => $plan->id, 'method' => 'card', 'status' => 'active', 'initial_charge_minor' => $plan->price_minor, 'payment_gateway' => 'flutterwave', 'started_at' => now(), 'renews_at' => now()->addMonth()]);
        $sub->subscriber()->associate($parent);
        $sub->save();

        $result = app(PaymentService::class)->process('flutterwave', 'renewal-1', 'sub_'.$sub->id, 'success', $plan->price_minor, []);
        $this->assertSame('subscription_needs_review', $result);
        $this->assertSame('needs_review', $sub->fresh()->status);
    }

    public function test_abandoned_checkout_expires_after_a_day_and_stays_out_of_history(): void
    {
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $plan = Plan::where('code', 'premium_individual')->firstOrFail();
        $pending = new Subscription(['plan_id' => $plan->id, 'method' => 'card', 'status' => 'pending', 'initial_charge_minor' => $plan->price_minor]);
        $pending->subscriber()->associate($parent);
        $pending->save();
        $pending->update(['created_at' => now()->subDays(2)]);

        $this->artisan('subscriptions:expire-checkouts')->assertSuccessful();
        $this->assertSame('expired', $pending->fresh()->status);
        $this->getJson('/api/v1/subscriptions')->assertOk()->assertJsonCount(0, 'data');
    }
}
