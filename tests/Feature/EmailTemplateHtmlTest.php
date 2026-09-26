<?php

namespace Tests\Feature;

use App\Models\EmailTemplateOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateHtmlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
    }

    public function test_admin_can_save_and_preview_a_rich_html_override(): void
    {
        $this->putJson('/api/v1/admin/email-templates/welcome', [
            'subject' => 'Hello from {{brand_name}}',
            'content_mode' => 'html',
            'include_branding' => true,
            'greeting' => null,
            'body' => '',
            'html_body' => '<h2>Welcome to {{brand_name}}</h2><p style="color:#123456">{{brand_tagline}}</p><a href="{{brand_url}}">Begin</a>',
            'action_text' => null,
            'action_url' => null,
        ])->assertOk()
            ->assertJsonPath('data.override.content_mode', 'html');

        $this->assertDatabaseHas('email_template_overrides', [
            'key' => 'welcome',
            'content_mode' => 'html',
        ]);

        $response = $this->getJson('/api/v1/admin/email-templates/welcome/preview')->assertOk();
        $html = (string) $response->json('data.html');

        $this->assertStringContainsString('Welcome to MAHADUM.360', $html);
        $this->assertStringContainsString((string) config('brand.tagline'), $html);
        $this->assertStringContainsString('color:#123456', $html);
        $this->assertStringContainsString((string) config('brand.url'), $html);
    }

    public function test_rich_html_removes_active_content_and_unsafe_urls(): void
    {
        $this->putJson('/api/v1/admin/email-templates/welcome', [
            'subject' => 'Safe welcome',
            'content_mode' => 'html',
            'include_branding' => true,
            'greeting' => null,
            'body' => '',
            'html_body' => '<script>alert(1)</script><p onclick="alert(2)" style="background:url(https://tracker.test/x)">Hello</p><a href="javascript:alert(3)">Bad link</a>',
            'action_text' => null,
            'action_url' => null,
        ])->assertOk();

        $stored = (string) EmailTemplateOverride::where('key', 'welcome')->value('html_body');
        $this->assertStringNotContainsString('<script', strtolower($stored));
        $this->assertStringNotContainsString('onclick=', strtolower($stored));
        $this->assertStringNotContainsString('javascript:', strtolower($stored));

        $response = $this->getJson('/api/v1/admin/email-templates/welcome/preview')->assertOk();
        $html = strtolower((string) $response->json('data.html'));

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('tracker.test', $html);
        $this->assertStringContainsString('<p>hello</p>', $html);
    }

    public function test_html_mode_requires_html_content(): void
    {
        $this->putJson('/api/v1/admin/email-templates/welcome', [
            'subject' => 'Incomplete',
            'content_mode' => 'html',
            'include_branding' => true,
            'body' => '',
            'html_body' => null,
            'action_text' => null,
            'action_url' => null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('html_body');
    }
}
