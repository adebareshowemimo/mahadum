<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Concerns\ResolvesFamily;
use App\Http\Controllers\Controller;
use App\Http\Requests\Family\FundWalletRequest;
use App\Http\Requests\Family\TransferRequest;
use App\Models\LearnerProfile;
use App\Models\WalletFundingTransaction;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\WalletFundingReconciler;
use App\Services\Family\FamilyAlertService;
use App\Services\Family\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    use ResolvesFamily;

    public function __construct(private WalletService $wallets, private PaymentGatewayManager $gateways) {}

    public function show(Request $request): JsonResponse
    {
        $wallet = $this->wallets->walletFor($this->family($request->user()));

        return response()->json(['data' => [
            'coin_balance' => $wallet->coin_balance,
            'currency_minor' => $wallet->currency_balance_minor,
            'currency' => $wallet->currency,
            'funding_gateways' => config('services.payments.wallet_funding_enabled') ? $this->gateways->available() : [],
            'funding_transactions' => WalletFundingTransaction::where('wallet_id', $wallet->id)
                ->latest()->limit(20)->get(['id', 'gateway', 'amount_minor', 'currency', 'status', 'created_at']),
        ]]);
    }

    public function confirm(Request $request, WalletFundingTransaction $funding, WalletFundingReconciler $reconciler): JsonResponse
    {
        $wallet = $this->wallets->walletFor($this->family($request->user()));
        abort_unless($funding->wallet_id === $wallet->id, 403, 'Not your wallet payment.');

        try {
            $status = $reconciler->reconcile($funding);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => [
                'code' => 'verification_unavailable',
                'message' => 'We could not verify this payment yet. Please do not pay again. Check this wallet later or contact support with the payment reference.',
                'status' => 503,
            ]], 503);
        }

        $message = match ($status) {
            'success' => 'Payment confirmed. Your wallet has been credited.',
            'failed' => 'Payment was not completed. No money was added.',
            'refunded' => 'This payment was refunded.',
            'needs_review' => 'Payment needs review. Your wallet has not been credited. Contact support with the payment reference.',
            default => 'Payment is still being confirmed. Please do not pay again. Check this wallet shortly.',
        };

        return response()->json(['data' => [
            'funding_id' => $funding->id,
            'gateway_ref' => $funding->gateway_ref,
            'status' => $status,
            'message' => $message,
            'currency_minor' => $wallet->fresh()->currency_balance_minor,
        ]]);
    }

    /**
     * Start a gateway top-up. We record a pending funding transaction and return
     * a checkout reference; the wallet is credited by the gateway WEBHOOK, never
     * by the client (idempotency middleware guards retries).
     */
    public function fund(FundWalletRequest $request): JsonResponse
    {
        abort_unless(config('services.payments.wallet_funding_enabled'), 503, 'Wallet funding is temporarily unavailable. Please try again later.');
        abort_unless(in_array($request->string('gateway')->value(), $this->gateways->available(), true), 422, 'This payment method is currently unavailable. Choose an available method or try again later.');
        $wallet = $this->wallets->walletFor($this->family($request->user()));

        $funding = WalletFundingTransaction::create([
            'wallet_id' => $wallet->id,
            'gateway' => $request->string('gateway'),
            'amount_minor' => $request->integer('amount'),
            'currency' => $wallet->currency,
            'status' => 'pending',
            'gateway_ref' => (string) Str::uuid(),
        ]);

        // Open the hosted checkout (NullGateway → null when no live gateway is on).
        $checkout = $this->gateways->driver($funding->gateway)->initialize(
            $funding->gateway_ref,
            $funding->amount_minor,
            (string) $request->user()->email,
            ['purpose' => 'wallet_funding', 'funding_id' => $funding->id],
        );
        if (config('services.payments.live') && (! is_string($checkout->checkoutUrl) || ! str_starts_with($checkout->checkoutUrl, 'https://'))) {
            $funding->update(['status' => 'failed']);
            abort(502, 'Secure checkout is unavailable. No money was added.');
        }

        // Record the gateway's own transaction id when it returns one, so later
        // events that don't echo our reference (e.g. Monnify refunds) still correlate.
        if ($checkout->providerReference !== null) {
            $funding->update(['gateway_txn_ref' => $checkout->providerReference]);
        }

        return response()->json(['data' => [
            'funding_id' => $funding->id,
            'status' => 'pending',
            'gateway' => $funding->gateway,
            'gateway_ref' => $funding->gateway_ref,
            // The client opens the gateway SDK/checkout with this reference.
            'checkout_url' => $checkout->checkoutUrl,
        ]], 201);
    }

    /** Move coins from the family wallet to a child's wallet. */
    public function transfer(TransferRequest $request): JsonResponse
    {
        $family = $this->family($request->user());
        $learner = LearnerProfile::where('family_id', $family->id)
            ->findOr($request->integer('to_learner_id'), fn () => abort(422, 'Learner is not in your family.'));

        $familyWallet = $this->wallets->walletFor($family);
        $learnerWallet = $this->wallets->walletFor($learner);

        if ($familyWallet->coin_balance < $request->integer('coins')) {
            return response()->json([
                'error' => ['code' => 'insufficient_coins', 'message' => 'Not enough coins to transfer.', 'status' => 422],
            ], 422);
        }

        $this->wallets->transfer($familyWallet, $learnerWallet, $request->integer('coins'), 'transfer', $learner->id);
        rescue(fn () => app(FamilyAlertService::class)->evaluate($family), report: true);

        return response()->json(['data' => [
            'family_balance' => $familyWallet->fresh()->coin_balance,
            'learner_balance' => $learnerWallet->fresh()->coin_balance,
        ]]);
    }
}
