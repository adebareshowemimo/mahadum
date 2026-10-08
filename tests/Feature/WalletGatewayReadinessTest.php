<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class WalletGatewayReadinessTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_wallet_lists_only_enabled_configured_gateways_and_never_exposes_secrets(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);
        config(['services.payments.live' => true, 'services.paystack.secret' => 'private-test-secret',
            'services.flutterwave.secret' => null, 'services.monnify.api_key' => null]);
        $response = $this->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('data.funding_gateways', ['paystack']);
        $this->assertStringNotContainsString('private-test-secret', $response->getContent());
        config(['services.payments.live' => false]);
        $this->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('data.funding_gateways', []);
    }

    public function test_unconfigured_gateway_is_rejected_before_recording_funding_or_contacting_provider(): void
    {
        Http::fake();
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);
        config(['services.payments.live' => true, 'services.paystack.secret' => null]);
        $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'paystack'], ['Idempotency-Key' => 'unconfigured'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('wallet_funding_transactions', 0);
        Http::assertNothingSent();
    }
}
