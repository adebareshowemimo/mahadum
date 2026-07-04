<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\EmailSuppression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendgridWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.sendgrid.webhook_token', 'secret-tok');
    }

    public function test_bounce_and_complaint_events_suppress_and_update_the_log(): void
    {
        EmailLog::create(['to_email' => 'bad@example.test', 'type' => 'marketing', 'status' => 'sent']);
        EmailLog::create(['to_email' => 'spammy@example.test', 'type' => 'marketing', 'status' => 'sent']);

        $this->postJson('/api/v1/webhooks/sendgrid/secret-tok', [
            ['email' => 'bad@example.test', 'event' => 'bounce', 'sg_event_id' => 'e1'],
            ['email' => 'spammy@example.test', 'event' => 'spamreport', 'sg_event_id' => 'e2'],
            ['email' => 'fine@example.test', 'event' => 'delivered', 'sg_event_id' => 'e3'],
        ])->assertOk();

        $this->assertDatabaseHas('email_suppressions', ['email' => 'bad@example.test', 'reason' => 'bounce']);
        $this->assertDatabaseHas('email_suppressions', ['email' => 'spammy@example.test', 'reason' => 'complaint']);
        // Delivered isn't a suppression event.
        $this->assertDatabaseMissing('email_suppressions', ['email' => 'fine@example.test']);
        // Log rows reflect the new status.
        $this->assertDatabaseHas('email_logs', ['to_email' => 'bad@example.test', 'status' => 'bounced']);
        $this->assertDatabaseHas('email_logs', ['to_email' => 'spammy@example.test', 'status' => 'complained']);
    }

    public function test_is_idempotent(): void
    {
        $payload = [['email' => 'x@example.test', 'event' => 'bounce', 'sg_event_id' => 'e1']];
        $this->postJson('/api/v1/webhooks/sendgrid/secret-tok', $payload)->assertOk();
        $this->postJson('/api/v1/webhooks/sendgrid/secret-tok', $payload)->assertOk();

        $this->assertSame(1, EmailSuppression::where('email', 'x@example.test')->count());
    }

    public function test_wrong_token_is_rejected(): void
    {
        $this->postJson('/api/v1/webhooks/sendgrid/wrong', [
            ['email' => 'x@example.test', 'event' => 'bounce'],
        ])->assertStatus(403);

        $this->assertDatabaseMissing('email_suppressions', ['email' => 'x@example.test']);
    }
}
