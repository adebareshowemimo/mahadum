<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenewalsReportTest extends TestCase
{
    use RefreshDatabase;

    private function subscribe(Plan $plan, User $user, string $renewsAt, string $status = 'active', ?string $remindedAt = null): Subscription
    {
        $sub = new Subscription([
            'plan_id' => $plan->id,
            'status' => $status,
            'method' => 'card',
            'started_at' => now(),
            'renews_at' => $renewsAt,
            'renewal_reminded_at' => $remindedAt,
        ]);
        $sub->subscriber()->associate($user);
        $sub->save();

        return $sub;
    }

    public function test_renewals_report_buckets_upcoming_and_sums_expected_revenue(): void
    {
        $this->seedRbac();
        $plan = Plan::create(['code' => 'fam', 'name' => 'Family', 'price_minor' => 500000, 'interval' => 'month']);

        // Two active subs renewing next month, one already reminded.
        $next = now()->addMonth();
        $this->subscribe($plan, User::factory()->create(), $next->toDateTimeString(), remindedAt: now()->toDateTimeString());
        $this->subscribe($plan, User::factory()->create(), $next->toDateTimeString());
        // A cancelled sub in-window must be ignored, and a past renewal excluded.
        $this->subscribe($plan, User::factory()->create(), $next->toDateTimeString(), status: 'cancelled');
        $this->subscribe($plan, User::factory()->create(), now()->subMonths(2)->toDateTimeString());

        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->getJson('/api/v1/admin/reports/renewals')
            ->assertOk()
            ->assertJsonPath('data.count.total', 2)
            ->assertJsonPath('data.revenue.total', 1000000) // 2 × ₦5,000
            ->assertJsonPath('data.by_method.card', 2)
            ->assertJsonPath('data.reminders.reminded', 1)
            ->assertJsonPath('data.reminders.total', 2);
    }

    public function test_renewals_report_requires_analytics_permission(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));

        $this->getJson('/api/v1/admin/reports/renewals')->assertStatus(403);
    }
}
