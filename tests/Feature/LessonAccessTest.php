<?php

namespace Tests\Feature;

use App\Models\Heart;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\EntitlementResolver;
use App\Services\Learning\LessonAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class LessonAccessTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_free_deep_links_are_denied_and_payment_then_cancellation_changes_access(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk();
        $lesson->update(['is_free_preview' => false]);
        $quiz = $lesson->components->firstWhere('type', 'quiz');
        $question = $quiz->quiz->questions->first();
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertForbidden();
        $this->postJson("/api/v1/components/{$quiz->id}/answer", [
            'learner_id' => $learner->id, 'question_id' => $question->id,
            'answer' => ['option_id' => $question->options->first()->id],
        ])->assertForbidden();
        $this->postJson("/api/v1/lessons/{$lesson->id}/complete", ['learner_id' => $learner->id])->assertForbidden();
        $this->getJson("/api/v1/lessons/{$lesson->id}")->assertForbidden();
        $subscription = $this->subscribe(User::class, $parent->id);
        Heart::create(['learner_profile_id' => $learner->id, 'current' => 0, 'competitive_paused_until' => now()->addHours(12)]);
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk()->assertJsonPath('data.hearts_remaining', null);
        $this->getJson("/api/v1/hearts?learner_id={$learner->id}")->assertOk()->assertJsonPath('data.unlimited_hearts', true);
        $subscription->update(['status' => 'cancelled']);
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertForbidden();
    }

    public function test_telco_is_level_one_only_and_card_subscription_takes_precedence(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $lesson->update(['is_free_preview' => false]);
        $this->subscribe(User::class, $parent->id, 'airtime');
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk();
        $lesson->courseLevel->update(['position' => 2]);
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertForbidden();
        $this->subscribe(User::class, $parent->id);
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk();
    }

    public function test_individual_and_school_subscriptions_provide_unlimited_hearts(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $learner->update(['user_id' => $parent->id]);
        $lesson = $this->publishedLesson();
        $lesson->update(['is_free_preview' => false]);
        $personal = $this->subscribe(User::class, $parent->id);
        $this->getJson("/api/v1/hearts?learner_id={$learner->id}")->assertOk()->assertJsonPath('data.current', null);
        $personal->update(['status' => 'cancelled']);
        $org = Organization::create(['name' => 'Test School', 'slug' => 'test-school', 'status' => 'active']);
        $learner->update(['organization_id' => $org->id]);
        $school = $this->subscribe(Organization::class, $org->id);
        $school->plan->update(['price_minor' => 0, 'audience' => 'school', 'features' => ['priced_per_seat' => true]]);
        $this->assertTrue(app(EntitlementResolver::class)->forLearner($learner->fresh())['unlimited_hearts']);
        $this->assertTrue(app(LessonAccess::class)->content($learner->fresh(), $lesson)['allowed']);
    }

    private function subscribe(string $type, int $id, string $method = 'card'): Subscription
    {
        $plan = Plan::firstOrCreate(['code' => 'access-test'], [
            'name' => 'Paid', 'price_minor' => 1000, 'currency' => 'NGN', 'interval' => 'month',
            'audience' => 'individual', 'features' => ['unlimited_hearts' => true],
        ]);

        return Subscription::create(['subscriber_type' => $type, 'subscriber_id' => $id,
            'plan_id' => $plan->id, 'status' => 'active', 'method' => $method, 'started_at' => now()]);
    }
}
