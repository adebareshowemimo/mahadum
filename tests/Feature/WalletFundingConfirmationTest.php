<?php

namespace Tests\Feature;

use App\Models\WalletFundingTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class WalletFundingConfirmationTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_flutterwave_unknown_reference_stays_pending_without_crediting(): void
    {
        $funding = $this->startFlutterwaveFunding();
        Http::fake(['*/transactions/verify_by_reference*' => Http::response([
            'status' => 'error', 'message' => 'No transaction was found for this id',
        ], 400)]);

        $this->postJson("/api/v1/wallet/fundings/{$funding->id}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertSame(0, $funding->wallet->fresh()->currency_balance_minor);
    }

    private function startFlutterwaveFunding(): WalletFundingTransaction
    {
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake(['*/payments' => Http::response(['data' => ['link' => 'https://checkout.flutterwave.test/wallet']])]);
        config([
            'app.frontend_url' => 'https://learn.example.test',
            'services.payments.live' => true,
            'services.flutterwave.enabled' => true,
            'services.flutterwave.public_key' => 'test-public',
            'services.flutterwave.secret' => 'test-secret',
            'services.flutterwave.encryption_key' => 'test-encryption',
            'services.flutterwave.secret_hash' => 'test-hash',
            'services.flutterwave.base_url' => 'https://api.flutterwave.test/v3',
        ]);
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);
        $id = $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'flutterwave'], ['Idempotency-Key' => 'flutterwave-wallet'])
            ->assertCreated()->json('data.funding_id');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/payments')
            && $request['redirect_url'] === "https://learn.example.test/payment/wallet/{$id}");

        return WalletFundingTransaction::findOrFail($id);
    }

    private function verifyResponse(WalletFundingTransaction $funding, array $changes = []): void
    {
        Http::fake(['*/transactions/verify_by_reference*' => Http::response(['status' => 'success', 'data' => array_replace([
            'id' => 987, 'status' => 'successful', 'tx_ref' => $funding->gateway_ref,
            'flw_ref' => 'FLW-987', 'currency' => 'NGN', 'amount' => 500,
        ], $changes)])]);
    }

    public function test_flutterwave_return_verifies_and_credits_once(): void
    {
        $funding = $this->startFlutterwaveFunding();
        $this->verifyResponse($funding);

        $this->postJson("/api/v1/wallet/fundings/{$funding->id}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'success')->assertJsonPath('data.currency_minor', 50000)
            ->assertJsonPath('data.amount_minor', 50000)
            ->assertJsonPath('data.gateway_ref', $funding->gateway_ref)
            ->assertJsonPath('data.gateway', 'flutterwave');
        $this->postJson("/api/v1/wallet/fundings/{$funding->id}/confirm")
            ->assertOk()->assertJsonPath('data.currency_minor', 50000);
        $this->assertSame('FLW-987', $funding->fresh()->gateway_txn_ref);
        $this->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('data.currency_minor', 50000);
    }

    public function test_flutterwave_mismatches_never_credit_the_wallet(): void
    {
        $funding = $this->startFlutterwaveFunding();
        foreach ([['amount' => 499], ['currency' => 'USD'], ['tx_ref' => 'someone-else']] as $changes) {
            $this->verifyResponse($funding, $changes);
            $this->postJson("/api/v1/wallet/fundings/{$funding->id}/confirm")
                ->assertOk()->assertJsonPath('data.status', 'needs_review');
        }
        $this->assertSame('pending', $funding->fresh()->status);
        $this->assertSame(0, $funding->wallet->fresh()->currency_balance_minor);
    }

    public function test_unconfirmed_flutterwave_payment_stays_pending(): void
    {
        $funding = $this->startFlutterwaveFunding();
        Http::fake(['*/transactions/verify_by_reference*' => Http::response(['status' => 'success', 'data' => [
            'tx_ref' => $funding->gateway_ref, 'status' => 'pending', 'currency' => 'NGN', 'amount' => 500,
        ]])]);

        $this->postJson("/api/v1/wallet/fundings/{$funding->id}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertSame(0, $funding->wallet->fresh()->currency_balance_minor);
    }

    public function test_monnify_return_uses_gateway_query_before_crediting(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([
            '*/api/v1/auth/login' => Http::response(['responseBody' => ['accessToken' => 'test-token']]),
            '*/init-transaction' => Http::response(['responseBody' => ['checkoutUrl' => 'https://sandbox.monnify.com/checkout/wallet', 'transactionReference' => 'MNFY-100']]),
        ]);
        config(['app.frontend_url' => 'https://learn.example.test', 'services.payments.live' => true,
            'services.monnify.api_key' => 'test-key', 'services.monnify.secret' => 'test-secret',
            'services.monnify.contract_code' => 'test-contract', 'services.monnify.base_url' => 'https://sandbox.monnify.com']);
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);
        $id = $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'monnify'], ['Idempotency-Key' => 'monnify-wallet-return'])
            ->assertCreated()->json('data.funding_id');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/init-transaction')
            && $request['redirectUrl'] === "https://learn.example.test/payment/wallet/{$id}");
        $funding = WalletFundingTransaction::findOrFail($id);
        Http::fake(['*/api/v2/merchant/transactions/query*' => function ($request) use ($funding) {
            $expired = str_contains($request->url(), 'expired-monnify');

            return Http::response(['responseBody' => [
                'paymentReference' => $expired ? 'expired-monnify' : $funding->gateway_ref,
                'transactionReference' => $expired ? null : 'MNFY-100',
                'paymentStatus' => $expired ? 'EXPIRED' : 'PAID',
                'amountPaid' => $expired ? '0.00' : '500.00', 'currency' => 'NGN',
            ]]);
        }]);

        $this->postJson("/api/v1/wallet/fundings/{$id}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'success')->assertJsonPath('data.currency_minor', 50000);

        $expired = WalletFundingTransaction::create([
            'wallet_id' => $funding->wallet_id, 'gateway' => 'monnify', 'amount_minor' => 50000,
            'currency' => 'NGN', 'status' => 'pending', 'gateway_ref' => 'expired-monnify',
        ]);
        $this->postJson("/api/v1/wallet/fundings/{$expired->id}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'failed')->assertJsonPath('data.currency_minor', 50000);
    }

    public function test_another_parent_cannot_confirm_someone_elses_funding(): void
    {
        $funding = $this->startFlutterwaveFunding();
        $other = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($other);

        $this->postJson("/api/v1/wallet/fundings/{$funding->id}/confirm")->assertForbidden();
        $this->assertSame('pending', $funding->fresh()->status);
    }

    public function test_wallet_checkout_is_hidden_and_blocked_while_disabled(): void
    {
        config(['services.payments.wallet_funding_enabled' => false]);
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);

        $this->getJson('/api/v1/wallet')->assertOk()->assertJsonCount(0, 'data.funding_gateways');
        $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'flutterwave'], ['Idempotency-Key' => 'disabled-wallet'])
            ->assertStatus(503);
        $this->assertDatabaseCount('wallet_funding_transactions', 0);
    }
}
