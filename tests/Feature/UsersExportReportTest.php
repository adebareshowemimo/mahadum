<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersExportReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_streams_a_csv_with_user_and_subscription_columns(): void
    {
        $this->seedRbac();

        $plan = Plan::create(['code' => 'fam', 'name' => 'Family', 'price_minor' => 500000, 'interval' => 'month']);
        $subscribed = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Obi', 'email' => 'ada@test.local', 'phone' => '+2348030000001', 'status' => 'active']);
        $sub = new Subscription([
            'plan_id' => $plan->id, 'status' => 'active', 'method' => 'card',
            'started_at' => now()->subDay(), 'renews_at' => now()->addMonth(),
        ]);
        $sub->subscriber()->associate($subscribed);
        $sub->save();

        User::factory()->create(['email' => 'free@test.local', 'status' => 'active']);

        $this->actingAsUser($this->userWithRole('super_admin'));

        $res = $this->get('/api/v1/admin/reports/users/export');
        $res->assertOk();
        $this->assertStringContainsString('text/csv', (string) $res->headers->get('content-type'));
        $this->assertStringContainsString('.csv', (string) $res->headers->get('content-disposition'));

        $csv = $res->streamedContent();
        $this->assertStringContainsString('Name,Email,Phone,', $csv);
        $this->assertStringContainsString('"Subscription plan"', $csv);
        $this->assertStringContainsString('"Ada Obi",ada@test.local,+2348030000001,active,Family,active,', $csv);
        $this->assertMatchesRegularExpression('/"Ada Obi".*,\d{4}-\d{2}-\d{2},\d{4}-\d{2}-\d{2},paid/', $csv);
        $this->assertStringContainsString('free@test.local,,active,,,,,none', $csv);

        $this->assertDatabaseHas('audit_logs', ['action' => 'report.users.exported']);
    }

    public function test_export_requires_analytics_permission(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));

        $this->get('/api/v1/admin/reports/users/export')->assertStatus(403);
    }

    public function test_subscription_records_show_the_latest_subscription_per_user_and_support_filters(): void
    {
        $this->seedRbac();

        $plan = Plan::create(['code' => 'individual', 'name' => 'Premium (Individual)', 'price_minor' => 250000, 'interval' => 'month']);
        $ada = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Obi', 'email' => 'ada@test.local', 'phone' => '+2348030000001', 'status' => 'active']);
        $lucy = User::factory()->create(['first_name' => 'Lucy', 'last_name' => 'Okafor', 'email' => 'lucy@test.local', 'status' => 'active']);
        User::factory()->create(['email' => 'free@test.local']);

        foreach ([
            [$ada, 'expired', now()->subMonths(2), now()->subMonth()],
            [$ada, 'active', now()->subDay(), now()->addMonth()],
            [$lucy, 'pending', null, null],
        ] as [$user, $status, $start, $expiry]) {
            $subscription = new Subscription([
                'plan_id' => $plan->id, 'status' => $status, 'method' => 'card',
                'started_at' => $start, 'renews_at' => $expiry,
            ]);
            $subscription->subscriber()->associate($user);
            $subscription->save();
        }

        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->getJson('/api/v1/admin/reports/subscriptions/records')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.name', 'Lucy Okafor')
            ->assertJsonPath('data.0.subscription_status', 'pending')
            ->assertJsonPath('data.0.subscription_start', null)
            ->assertJsonPath('data.0.subscription_expiry', null)
            ->assertJsonPath('data.0.payment_status', 'pending')
            ->assertJsonPath('data.1.name', 'Ada Obi')
            ->assertJsonPath('data.1.phone', '+2348030000001')
            ->assertJsonPath('data.1.subscription_plan', 'Premium (Individual)')
            ->assertJsonPath('data.1.subscription_status', 'active')
            ->assertJsonPath('data.1.payment_status', 'paid');

        $this->getJson('/api/v1/admin/reports/subscriptions/records?q=ada@test.local&status=active')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Ada Obi');
        $this->getJson('/api/v1/admin/reports/subscriptions/records?q=Ada%20Obi')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Ada Obi');
        $this->getJson('/api/v1/admin/reports/subscriptions/records?status=pending')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Lucy Okafor');
    }

    public function test_subscription_records_require_analytics_permission(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));

        $this->getJson('/api/v1/admin/reports/subscriptions/records')->assertForbidden();
    }
}
