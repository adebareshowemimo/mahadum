<?php

namespace Tests\Feature;

use App\Mail\ConfigurationTestMail;
use App\Models\Setting;
use App\Services\Billing\PaymentGatewayManager;
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
            'scheme' => 'smtp',
            'username' => 'apikey',
            'password' => 'smtp-secret-value',
            'from_address' => 'hello@mahadum.test',
            'from_name' => 'MAHADUM.360',
        ];
    }

    public function test_super_admin_can_choose_the_data_default_without_changing_payment_default(): void
    {
        $this->superAdmin();
        $this->putJson('/api/v1/admin/payment-gateways/data-default', ['provider' => 'flutterwave'])
            ->assertOk()->assertJsonPath('data.data_default_provider', 'flutterwave');
        $this->assertSame('flutterwave', app(IntegrationSettings::class)->get('data_bundles.default_provider'));
        $this->assertSame('monnify', config('services.payments.default'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'billing.data_bundle.default_provider_updated']);
        $this->putJson('/api/v1/admin/payment-gateways/data-default', ['provider' => 'paystack'])->assertUnprocessable();
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

    public function test_flutterwave_can_be_saved_independently_without_exposing_secrets(): void
    {
        $this->superAdmin();

        $this->putJson('/api/v1/admin/payment-gateways/flutterwave', [
            'enabled' => false,
            'bills_enabled' => false,
            'public_key' => 'FLWPUBK_TEST-example',
            'secret' => 'FLWSECK_TEST-example',
            'encryption_key' => 'FLWSECK_TESTencryption',
            'secret_hash' => 'separate-webhook-hash',
        ])->assertOk()
            ->assertJsonPath('data.default', 'monnify')
            ->assertJsonPath('data.providers.2.configured', true)
            ->assertJsonPath('data.providers.2.enabled', false)
            ->assertJsonPath('data.providers.2.environment', 'sandbox')
            ->assertJsonMissing(['secret' => 'FLWSECK_TEST-example'])
            ->assertJsonMissing(['secret_hash' => 'separate-webhook-hash']);

        $stored = Setting::where('key', 'integration.flutterwave.secret')->value('value');
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('FLWSECK_TEST-example', $stored);
        $storedEncryption = Setting::where('key', 'integration.flutterwave.encryption_key')->value('value');
        $this->assertIsString($storedEncryption);
        $this->assertStringNotContainsString('FLWSECK_TESTencryption', $storedEncryption);
        config(['services.payments.live' => true]);
        $this->assertNotContains('flutterwave', app(PaymentGatewayManager::class)->available());

        $this->putJson('/api/v1/admin/payment-gateways/flutterwave', [
            'enabled' => true,
            'bills_enabled' => false,
        ])->assertOk()
            ->assertJsonPath('data.default', 'monnify')
            ->assertJsonPath('data.providers.2.enabled', true)
            ->assertJsonPath('data.providers.2.bills_enabled', false);
        config(['services.payments.live' => true]);
        $this->assertContains('flutterwave', app(PaymentGatewayManager::class)->available());

        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.flutterwave_configuration_updated']);
    }

    public function test_flutterwave_data_catalogue_check_reports_provider_whitelist_error_without_purchase(): void
    {
        $this->superAdmin();
        $this->putJson('/api/v1/admin/payment-gateways/flutterwave', [
            'enabled' => false,
            'bills_enabled' => false,
            'public_key' => 'FLWPUBK_TEST-example',
            'secret' => 'FLWSECK_TEST-example',
            'encryption_key' => 'FLWSECK_TESTencryption',
            'secret_hash' => 'test-webhook-hash',
        ])->assertOk();
        Http::fake([
            'api.flutterwave.com/v3/top-bill-categories*' => Http::response([
                'status' => 'error',
                'message' => 'Please enable IP Whitelisting to access this service',
            ], 400),
        ]);

        $this->postJson('/api/v1/admin/payment-gateways/flutterwave/test')
            ->assertOk()
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.message', 'Flutterwave bill catalogue rejected the request (HTTP 400): Please enable IP Whitelisting to access this service');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/top-bill-categories?country=NG'));
    }

    public function test_replacing_flutterwave_key_requires_the_three_matching_api_keys(): void
    {
        $this->superAdmin();
        $this->putJson('/api/v1/admin/payment-gateways/flutterwave', [
            'enabled' => false,
            'bills_enabled' => false,
            'secret' => 'FLWSECK_TEST-new-key',
        ])->assertUnprocessable()->assertJsonValidationErrors('public_key');
        $this->assertDatabaseMissing('settings', ['key' => 'integration.flutterwave.secret']);
    }

    public function test_saving_three_new_keys_clears_an_old_webhook_hash_until_replaced(): void
    {
        $this->superAdmin();
        config([
            'services.flutterwave.secret' => 'FLWSECK-old-live',
            'services.flutterwave.secret_hash' => 'old-live-webhook-hash',
        ]);

        $this->putJson('/api/v1/admin/payment-gateways/flutterwave', [
            'enabled' => false,
            'bills_enabled' => false,
            'public_key' => 'FLWPUBK_TEST-example',
            'secret' => 'FLWSECK_TEST-example',
            'encryption_key' => 'FLWSECK_TESTencryption',
        ])->assertOk()
            ->assertJsonPath('data.providers.2.configured', false)
            ->assertJsonPath('data.providers.2.requirements.3.set', false);

        $this->assertSame('', app(IntegrationSettings::class)->get('flutterwave.secret_hash'));
    }
}
