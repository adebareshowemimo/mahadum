<?php

namespace Tests\Feature;

use App\Mail\CampaignMail;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailCampaign;
use App\Models\EmailLog;
use App\Models\EmailSuppression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailCampaignTest extends TestCase
{
    use RefreshDatabase;

    private function draft(array $attrs = []): EmailCampaign
    {
        return EmailCampaign::create(array_merge([
            'subject' => 'Big news',
            'body' => 'Hello **friends**, we have news.',
            'audience_type' => 'contact_list',
            'status' => 'draft',
        ], $attrs));
    }

    public function test_send_to_a_contact_list_skips_suppressed_and_logs_each(): void
    {
        $this->seedRbac();
        $list = ContactList::create(['name' => 'Newsletter']);
        Contact::create(['contact_list_id' => $list->id, 'email' => 'a@example.test', 'status' => 'subscribed']);
        Contact::create(['contact_list_id' => $list->id, 'email' => 'b@example.test', 'status' => 'subscribed']);
        Contact::create(['contact_list_id' => $list->id, 'email' => 'gone@example.test', 'status' => 'unsubscribed']);
        Contact::create(['contact_list_id' => $list->id, 'email' => 'blocked@example.test', 'status' => 'subscribed']);
        EmailSuppression::create(['email' => 'blocked@example.test', 'reason' => 'bounce']);

        $campaign = $this->draft(['audience_type' => 'contact_list', 'audience' => ['contact_list_id' => $list->id]]);
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->postJson("/api/v1/admin/email-campaigns/{$campaign->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.sent_count', 2); // a + b (unsubscribed excluded, blocked suppressed)

        // Recipient rows: 2 sent + 1 suppressed (the unsubscribed contact isn't a recipient).
        $this->assertDatabaseHas('email_campaign_recipients', ['email' => 'a@example.test', 'status' => 'sent']);
        $this->assertDatabaseHas('email_campaign_recipients', ['email' => 'blocked@example.test', 'status' => 'suppressed']);
        // Each real send is recorded in the email log as marketing from this campaign.
        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'a@example.test', 'type' => 'marketing', 'source' => "campaign:{$campaign->id}",
        ]);
    }

    public function test_send_to_a_user_segment_filters_by_role(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $this->userWithRole('teacher'); // excluded by the role filter
        $campaign = $this->draft(['audience_type' => 'user_segment', 'audience' => ['role' => 'parent']]);
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->postJson("/api/v1/admin/email-campaigns/{$campaign->id}/send")
            ->assertOk()->assertJsonPath('data.sent_count', 1);

        $this->assertDatabaseHas('email_campaign_recipients', ['email' => $parent->email, 'user_id' => $parent->id]);
    }

    public function test_scheduling_defers_the_send(): void
    {
        $this->seedRbac();
        $list = ContactList::create(['name' => 'L']);
        Contact::create(['contact_list_id' => $list->id, 'email' => 'x@example.test', 'status' => 'subscribed']);
        $campaign = $this->draft(['audience_type' => 'contact_list', 'audience' => ['contact_list_id' => $list->id]]);
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->postJson("/api/v1/admin/email-campaigns/{$campaign->id}/send", ['scheduled_at' => now()->addHour()->toIso8601String()])
            ->assertOk()->assertJsonPath('data.status', 'scheduled');

        $this->assertSame(0, $campaign->fresh()->recipients()->count());

        // Nothing due yet; then move the clock and run the dispatcher.
        $this->artisan('emails:dispatch-scheduled')->assertSuccessful();
        $this->assertSame('scheduled', $campaign->fresh()->status);

        $campaign->update(['scheduled_at' => now()->subMinute()]);
        $this->artisan('emails:dispatch-scheduled')->assertSuccessful();
        $this->assertSame('sent', $campaign->fresh()->status);
    }

    public function test_cannot_send_twice(): void
    {
        $this->seedRbac();
        $list = ContactList::create(['name' => 'L']);
        Contact::create(['contact_list_id' => $list->id, 'email' => 'x@example.test', 'status' => 'subscribed']);
        $campaign = $this->draft(['audience_type' => 'contact_list', 'audience' => ['contact_list_id' => $list->id], 'status' => 'sent']);
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->postJson("/api/v1/admin/email-campaigns/{$campaign->id}/send")->assertStatus(409);
    }

    public function test_test_send_goes_to_the_admin_only(): void
    {
        $this->seedRbac();
        Mail::fake();
        $campaign = $this->draft(['audience' => ['contact_list_id' => null]]);
        $admin = $this->userWithRole('super_admin');
        $this->actingAsUser($admin);

        $this->postJson("/api/v1/admin/email-campaigns/{$campaign->id}/test")
            ->assertOk()->assertJsonPath('data.sent_to', $admin->email);

        // CampaignMail is ShouldQueue, so a faked mailer records it as queued.
        Mail::assertQueued(CampaignMail::class);
    }

    public function test_campaigns_are_super_admin_only(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));
        $this->getJson('/api/v1/admin/email-campaigns')->assertStatus(403);
    }

    public function test_signed_unsubscribe_link_suppresses_and_unsubscribes(): void
    {
        $list = ContactList::create(['name' => 'L']);
        Contact::create(['contact_list_id' => $list->id, 'email' => 'leaver@example.test', 'status' => 'subscribed']);

        $url = URL::signedRoute('email.unsubscribe', ['email' => 'leaver@example.test']);
        $this->get($url)->assertOk()->assertSee('unsubscribed', false);

        $this->assertDatabaseHas('email_suppressions', ['email' => 'leaver@example.test', 'reason' => 'unsubscribe']);
        $this->assertDatabaseHas('contacts', ['email' => 'leaver@example.test', 'status' => 'unsubscribed']);
        // A tampered (unsigned) link is rejected.
        $this->get('/email/unsubscribe/nope@example.test')->assertStatus(403);
    }

    public function test_email_log_endpoint_lists_sends_for_super_admin(): void
    {
        $this->seedRbac();
        EmailLog::create(['to_email' => 'seen@example.test', 'type' => 'transactional', 'source' => 'welcome', 'status' => 'sent']);
        $this->actingAsUser($this->userWithRole('super_admin'));

        $this->getJson('/api/v1/admin/email-log?q=seen')
            ->assertOk()
            ->assertJsonPath('data.0.to_email', 'seen@example.test')
            ->assertJsonPath('data.0.source', 'welcome');
    }
}
