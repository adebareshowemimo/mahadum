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
}
