<?php

namespace Tests\Feature;

use App\Mail\ConfigurationTestMail;
use App\Models\Setting;
use App\Services\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminIntegrationConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
    }

    /** @return array<string, mixed> */
    private function smtpPayload(): array
    {
        return [
            'mailer' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 587,
            'scheme' => 'tls',
            'username' => 'apikey',
            'password' => 'smtp-secret-value',
            'from_address' => 'hello@mahadum.test',
            'from_name' => 'MAHADUM.360',
        ];
    }

    public function test_super_admin_can_save_encrypted_smtp_configuration(): void
    {
        $this->superAdmin();

        $this->putJson('/api/v1/admin/email-configuration', $this->smtpPayload())
            ->assertOk()
            ->assertJsonPath('data.delivery_enabled', true)
            ->assertJsonPath('data.password_set', true)
            ->assertJsonMissingPath('data.password');

        $stored = Setting::where('key', 'integration.mail.password')->value('value');
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('smtp-secret-value', $stored);
        $this->assertSame('smtp-secret-value', app(IntegrationSettings::class)->get('mail.password'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'email.configuration_updated']);
    }

    public function test_admin_can_send_an_immediate_test_email(): void
    {
        $this->superAdmin();
        $this->putJson('/api/v1/admin/email-configuration', $this->smtpPayload())->assertOk();
        Mail::fake();

        $this->postJson('/api/v1/admin/email-configuration/test', ['email' => 'operator@example.test'])
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        Mail::assertSent(ConfigurationTestMail::class, fn ($mail) => $mail->hasTo('operator@example.test'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'email.configuration_tested']);
    }

    public function test_log_mailer_cannot_claim_to_deliver_a_test_email(): void
    {
        $this->superAdmin();

        $payload = $this->smtpPayload();
        $payload['mailer'] = 'log';
        $this->putJson('/api/v1/admin/email-configuration', $payload)->assertOk();

        $this->postJson('/api/v1/admin/email-configuration/test', ['email' => 'operator@example.test'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Email delivery is disabled. Save an SMTP configuration before testing.']);
    }

    public function test_super_admin_can_configure_and_test_monnify_without_exposing_secrets(): void
    {
        $this->superAdmin();
        Http::fake([
            'sandbox.monnify.com/api/v1/auth/login' => Http::response([
                'requestSuccessful' => true,
                'responseMessage' => 'success',
                'responseBody' => ['accessToken' => 'access-token'],
            ]),
        ]);

        $this->putJson('/api/v1/admin/payment-gateways/monnify', [
            'live' => true,
            'environment' => 'sandbox',
            'api_key' => 'MK_TEST_ADMIN',
            'secret' => 'monnify-secret-value',
            'contract_code' => '123456789',
        ])
            ->assertOk()
            ->assertJsonPath('data.live', true)
            ->assertJsonPath('data.default', 'monnify')
            ->assertJsonPath('data.providers.0.configured', true)
            ->assertJsonPath('data.providers.0.environment', 'sandbox')
            ->assertJsonMissing(['secret' => 'monnify-secret-value']);

        $stored = Setting::where('key', 'integration.monnify.secret')->value('value');
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('monnify-secret-value', $stored);

        $this->postJson('/api/v1/admin/payment-gateways/monnify/test')
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        Http::assertSent(fn ($request) => $request->hasHeader(
            'Authorization',
            'Basic '.base64_encode('MK_TEST_ADMIN:monnify-secret-value'),
        ));
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.monnify_configuration_updated']);
    }

    public function test_non_admin_cannot_read_integration_configuration(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('parent'));

        $this->getJson('/api/v1/admin/email-configuration')->assertForbidden();
        $this->getJson('/api/v1/admin/payment-gateways')->assertForbidden();
    }
}
