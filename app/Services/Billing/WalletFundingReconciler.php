<?php

namespace App\Services\Billing;

use App\Models\WalletFundingTransaction;

class WalletFundingReconciler
{
    public function __construct(private PaymentGatewayManager $gateways, private PaymentService $payments) {}

    /** Verify with the selected gateway before changing a wallet balance. */
    public function reconcile(WalletFundingTransaction $funding): string
    {
        if ($funding->status !== 'pending') {
            return $funding->status;
        }

        if (! in_array($funding->gateway, ['flutterwave', 'monnify', 'paystack'], true)) {
            return 'needs_review';
        }

        $verified = $this->gateways->driver($funding->gateway)->verify((string) $funding->gateway_ref);
        if ($verified->status === 'pending') {
            return 'pending';
        }

        $data = $funding->gateway === 'monnify'
            ? ($verified->raw['responseBody'] ?? [])
            : ($verified->raw['data'] ?? []);
        if (! is_array($data)) {
            return 'needs_review';
        }
        $reference = $funding->gateway === 'monnify'
            ? ($data['paymentReference'] ?? null)
            : ($data[$funding->gateway === 'flutterwave' ? 'tx_ref' : 'reference'] ?? null);
        $currency = $funding->gateway === 'monnify'
            ? ($data['currencyCode'] ?? null)
            : ($data['currency'] ?? null);

        if ($reference !== $funding->gateway_ref || $currency !== $funding->currency) {
            return 'needs_review';
        }

        if ($verified->status === 'failed') {
            WalletFundingTransaction::whereKey($funding->id)->where('status', 'pending')->update(['status' => 'failed']);

            return $funding->fresh()->status;
        }

        if ($verified->amountMinor !== $funding->amount_minor) {
            return 'needs_review';
        }

        $transactionReference = $funding->gateway === 'monnify'
            ? ($data['transactionReference'] ?? null)
            : ($funding->gateway === 'flutterwave' ? ($data['flw_ref'] ?? null) : null);
        if (is_string($transactionReference) && $transactionReference !== '') {
            WalletFundingTransaction::whereKey($funding->id)->whereNull('gateway_txn_ref')
                ->update(['gateway_txn_ref' => $transactionReference]);
        }

        $this->payments->process(
            $funding->gateway,
            'wallet_verify_'.$funding->id,
            $funding->gateway_ref,
            'success',
            $verified->amountMinor,
            $verified->raw,
        );

        return $funding->fresh()->status === 'success' ? 'success' : 'needs_review';
    }
}
