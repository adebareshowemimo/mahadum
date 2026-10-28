<?php

namespace App\Services\Billing;

use App\Models\StoryPurchase;
use App\Services\AuditLogger;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Facades\DB;

class StoryPaymentService
{
    public function __construct(private ReferralService $referrals, private AuditLogger $audit) {}

    public function settle(StoryPurchase $purchase, string $source, ?int $amountMinor, array $raw, string $sourceEvent): bool
    {
        $paid = DB::transaction(function () use ($purchase, $source, $amountMinor, $raw) {
            $purchase = StoryPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($purchase->provider !== $source || $amountMinor !== $purchase->amount_minor
                || $purchase->amount_minor !== $purchase->story->price_minor
                || $this->currency($source, $raw) !== 'NGN') {
                return false;
            }
            if ($purchase->status === 'paid') {
                return false;
            }
            if ($purchase->status !== 'pending') {
                return false;
            }
            $gatewayReference = match ($source) {
                'flutterwave' => $raw['data']['flw_ref'] ?? null,
                'monnify' => $raw['eventData']['transactionReference'] ?? $raw['responseBody']['transactionReference'] ?? null,
                'paystack' => isset($raw['data']['id']) ? (string) $raw['data']['id'] : null,
                default => null,
            };
            $purchase->update(['status' => 'paid', 'paid_at' => now(),
                'gateway_txn_ref' => $gatewayReference ?? $purchase->gateway_txn_ref]);

            return true;
        });

        if ($paid) {
            $this->referrals->recordReferredPurchase($purchase->user, $purchase, $purchase->amount_minor, $sourceEvent);
            $this->audit->record('story.purchase_paid', $purchase, ['status' => 'pending'], ['status' => 'paid']);
        }

        return $paid;
    }

    public function refund(StoryPurchase $purchase, string $source, ?int $amountMinor): bool
    {
        if ($purchase->provider === $source && $purchase->status === 'paid' && $amountMinor !== $purchase->amount_minor) {
            $this->audit->record('story.partial_refund_requires_review', $purchase, [],
                ['refund_amount_minor' => $amountMinor, 'purchase_amount_minor' => $purchase->amount_minor]);

            return false;
        }
        $refunded = DB::transaction(function () use ($purchase, $source, $amountMinor) {
            $purchase = StoryPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($purchase->provider !== $source || $purchase->status !== 'paid'
                || $amountMinor !== $purchase->amount_minor) {
                return false;
            }
            $purchase->update(['status' => 'refunded']);

            return true;
        });
        if ($refunded) {
            $this->referrals->reverseForSource(StoryPurchase::class, $purchase->id);
            $this->audit->record('story.purchase_refunded', $purchase, ['status' => 'paid'], ['status' => 'refunded']);
        }

        return $refunded;
    }

    private function currency(string $source, array $raw): ?string
    {
        return match ($source) {
            'monnify' => $raw['eventData']['currencyCode'] ?? $raw['responseBody']['currencyCode'] ?? $raw['responseBody']['currency'] ?? null,
            default => $raw['data']['currency'] ?? null,
        };
    }
}
