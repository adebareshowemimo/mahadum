<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Concerns\ResolvesFamily;
use App\Http\Controllers\Controller;
use App\Http\Requests\Family\FundWalletRequest;
use App\Http\Requests\Family\TransferRequest;
use App\Models\LearnerProfile;
use App\Models\WalletFundingTransaction;
use App\Services\Billing\PaymentGatewayManager;
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
        ]]);
    }

    /**
     * Start a gateway top-up. We record a pending funding transaction and return
     * a checkout reference; the wallet is credited by the gateway WEBHOOK, never
     * by the client (idempotency middleware guards retries).
     */
    public function fund(FundWalletRequest $request): JsonResponse
    {
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

        return response()->json(['data' => [
            'family_balance' => $familyWallet->fresh()->coin_balance,
            'learner_balance' => $learnerWallet->fresh()->coin_balance,
        ]]);
    }
}
