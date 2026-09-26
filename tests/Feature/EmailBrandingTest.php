<?php

namespace Tests\Feature;

use App\Mail\CampaignMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
    }

    public function test_admin_can_manage_and_preview_sanitized_email_branding(): void
    {
        $response = $this->putJson('/api/v1/admin/email-branding', [
            'enabled' => true,
            'header_enabled' => true,
            'footer_enabled' => true,
            'header_html' => '<h1 onclick="alert(1)">Custom Header</h1><script>alert(2)</script>',
            'footer_html' => '<p>Custom Footer</p>',
        ])->assertOk()
            ->assertJsonPath('data.enabled', true);

        $html = (string) $response->json('data.preview_html');
        $this->assertStringContainsString('Custom Header', $html);
        $this->assertStringContainsString('Custom Footer', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertDatabaseHas('audit_logs', ['action' => 'email_branding.updated']);
    }

    public function test_template_can_exclude_the_managed_header_and_footer(): void
    {
        $this->putJson('/api/v1/admin/email-branding', [
            'enabled' => true,
            'header_enabled' => true,
            'footer_enabled' => true,
            'header_html' => '<p>Unique Managed Header</p>',
            'footer_html' => '<p>Unique Managed Footer</p>',
        ])->assertOk();

        $this->putJson('/api/v1/admin/email-templates/welcome', [
            'subject' => 'Plain welcome',
            'content_mode' => 'html',
            'include_branding' => false,
            'greeting' => null,
            'body' => '',
            'html_body' => '<p>Template content remains</p>',
            'action_text' => null,
            'action_url' => null,
        ])->assertOk()
            ->assertJsonPath('data.override.include_branding', false);

        $html = (string) $this->getJson('/api/v1/admin/email-templates/welcome/preview')
            ->assertOk()
            ->json('data.html');

        $this->assertStringContainsString('Template content remains', $html);
        $this->assertStringNotContainsString('Unique Managed Header', $html);
        $this->assertStringNotContainsString('Unique Managed Footer', $html);
    }

    public function test_global_switch_hides_branding_but_keeps_campaign_unsubscribe(): void
    {
        $this->putJson('/api/v1/admin/email-branding', [
            'enabled' => false,
            'header_enabled' => true,
            'footer_enabled' => true,
            'header_html' => '<p>Hidden Header</p>',
            'footer_html' => '<p>Hidden Footer</p>',
        ])->assertOk();

        $campaign = new CampaignMail('News', 'Campaign body', 'https://example.test/unsubscribe', 1);
        $html = (string) $campaign->render();

        $this->assertStringNotContainsString('Hidden Header', $html);
        $this->assertStringNotContainsString('Hidden Footer', $html);
        $this->assertStringContainsString('Campaign body', $html);
        $this->assertStringContainsString('Unsubscribe', $html);
    }

    public function test_framework_authentication_emails_use_managed_branding(): void
    {
        $this->putJson('/api/v1/admin/email-branding', [
            'enabled' => true,
            'header_enabled' => true,
            'footer_enabled' => true,
            'header_html' => '<p>Authentication Header</p>',
            'footer_html' => '<p>Authentication Footer</p>',
        ])->assertOk();

        $user = User::factory()->create();
        $verification = (string) (new VerifyEmail)->toMail($user)->render();
        $reset = (string) (new ResetPassword('test-token'))->toMail($user)->render();

        foreach ([$verification, $reset] as $html) {
            $this->assertStringContainsString('Authentication Header', $html);
            $this->assertStringContainsString('Authentication Footer', $html);
        }
    }
}
