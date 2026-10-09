<?php

namespace Tests\Feature;

use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Models\WalletFundingTransaction;
use App\Services\Billing\PaymentService;
use App\Services\Family\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class WalletSettlementIntegrityTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function funding(): WalletFundingTransaction
    {
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([
            '*/api/v1/auth/login' => Http::response(['responseBody' => ['accessToken' => 'test-token']]),
            '*/init-transaction' => Http::response(['responseBody' => ['checkoutUrl' => 'https://sandbox.monnify.com/checkout/wallet', 'transactionReference' => 'MNFY-WALLET']]),
        ]);
        config(['services.payments.live' => true, 'services.monnify.api_key' => 'test-key',
            'services.monnify.secret' => 'test-secret', 'services.monnify.contract_code' => 'test-contract',
            'services.monnify.base_url' => 'https://sandbox.monnify.com', 'services.paystack.secret' => 'paystack-test']);
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);
        $id = $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'monnify'], ['Idempotency-Key' => 'integrity-wallet'])
            ->assertCreated()->assertJsonPath('data.checkout_url', 'https://sandbox.monnify.com/checkout/wallet')->json('data.funding_id');

        return WalletFundingTransaction::findOrFail($id);
    }

    private function paid(WalletFundingTransaction $funding, array $changes = [])
    {
        $body = json_encode(['eventType' => 'SUCCESSFUL_TRANSACTION', 'eventData' => array_replace([
            'paymentReference' => $funding->gateway_ref, 'transactionReference' => 'MNFY-WALLET',
            'paymentStatus' => 'PAID', 'amountPaid' => 500, 'currencyCode' => 'NGN',
        ], $changes)]);

        return $this->call('POST', '/api/v1/webhooks/monnify', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_MONNIFY_SIGNATURE' => hash_hmac('sha512', $body, 'test-secret'),
        ], $body);
    }

    public static function mismatchedPayments(): array
    {
        return ['underpaid' => [['amountPaid' => 100]], 'missing amount' => [['amountPaid' => null]],
            'wrong currency' => [['currencyCode' => 'USD']]];
    }

    #[DataProvider('mismatchedPayments')]
    public function test_mismatched_signed_payment_cannot_credit_wallet(array $changes): void
    {
        $funding = $this->funding();
        $this->paid($funding, $changes)->assertOk()->assertJsonPath('status', 'ignored');
        $this->assertSame('pending', $funding->fresh()->status);
        $this->assertSame(0, $funding->wallet->fresh()->currency_balance_minor);
    }

    public function test_other_signed_gateway_cannot_settle_monnify_funding(): void
    {
        $funding = $this->funding();
        $body = json_encode(['event' => 'charge.success', 'data' => ['id' => 99, 'reference' => $funding->gateway_ref,
            'status' => 'success', 'amount' => 50000, 'currency' => 'NGN']]);
        $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'paystack-test'),
        ], $body)->assertOk()->assertJsonPath('status', 'ignored');
        $this->assertSame('pending', $funding->fresh()->status);
        $this->assertSame(0, $funding->wallet->fresh()->currency_balance_minor);
    }

    public function test_checkout_and_signed_replays_credit_once_and_never_recredit_refunded_funding(): void
    {
        $funding = $this->funding();
        $this->assertSame(0, $funding->wallet->currency_balance_minor);
        $this->paid($funding)->assertOk()->assertJsonPath('status', 'funded');
        $this->paid($funding)->assertOk()->assertJsonPath('status', 'duplicate');
        $this->paid($funding, ['transactionReference' => 'another-notification'])->assertOk();
        $this->assertSame(50000, $funding->wallet->fresh()->currency_balance_minor);
        app(PaymentService::class)->process('monnify', 'refund-once', $funding->gateway_ref, 'refund', 50000, []);
        $this->paid($funding, ['transactionReference' => 'late-success'])->assertOk()->assertJsonPath('status', 'ignored');
        $this->assertSame('refunded', $funding->fresh()->status);
        $this->assertSame(0, $funding->wallet->fresh()->currency_balance_minor);
    }

    public function test_credit_failure_rolls_back_funding_and_retry_can_credit_once(): void
    {
        $funding = $this->funding();
        $calls = 0;
        $this->partialMock(WalletService::class)->shouldReceive('creditCurrency')->twice()
            ->andReturnUsing(function ($wallet, $amount) use (&$calls) {
                if (++$calls === 1) {
                    throw new \RuntimeException('Simulated credit failure');
                }

                return (new WalletService)->creditCurrency($wallet, $amount);
            });
        // Resolve a new payment processor after installing the wallet failure.
        $this->app->forgetInstance(PaymentWebhookController::class);
        $this->paid($funding)->assertStatus(500);
        $this->assertSame('pending', $funding->fresh()->status);
        $this->assertSame(0, $funding->wallet->fresh()->currency_balance_minor);
        $this->paid($funding)->assertOk()->assertJsonPath('status', 'funded');
        $this->assertSame(50000, $funding->wallet->fresh()->currency_balance_minor);
    }

    public function test_receipt_failure_does_not_fail_or_repeat_a_committed_credit(): void
    {
        $funding = $this->funding();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Receipt queue unavailable'));
        $this->paid($funding)->assertOk()->assertJsonPath('status', 'funded');
        $this->paid($funding)->assertOk()->assertJsonPath('status', 'duplicate');
        $this->assertSame('success', $funding->fresh()->status);
        $this->assertSame(50000, $funding->wallet->fresh()->currency_balance_minor);
    }
}
