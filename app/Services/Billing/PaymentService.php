<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletFundingTransaction;
use App\Models\WebhookEvent;
use App\Notifications\SubscriptionActivated;
use App\Services\AuditLogger;
use App\Services\Family\WalletService;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Carbon;

/**
 * Processes a normalized payment event from any gateway. Idempotent via the
 * webhook_events table (same source+event id is never processed twice), and
 * correlates the gateway reference to either a wallet funding or a subscription.
 *
 * Money is credited HERE (server-side, webhook-driven) — never by the client.
 */
class PaymentService
{
    public function __construct(private WalletService $wallets, private ReferralService $referrals, private AuditLogger $audit) {}

    /**
     * @return string outcome: duplicate|ignored|funded|subscription_active|unmatched
     */
    /**
     * @param  string  $kind  normalized event kind: success|refund|ignored
     * @return string outcome: duplicate|ignored|funded|subscription_active|reversed|unmatched
     */
    public function process(string $source, string $eventKey, ?string $reference, string $kind, ?int $amountMinor, array $raw): string
    {
        $event = WebhookEvent::firstOrNew(['source' => $source, 'event' => $eventKey]);

        if ($event->exists && $event->processed_at) {
            return 'duplicate';
        }

        $event->payload = $raw;
        $event->status = 'received';
        $event->save();

        if ($kind === 'ignored') {
            $event->update(['status' => 'ignored', 'processed_at' => now()]);

            return 'ignored';
        }

        $outcome = $kind === 'refund'
            ? $this->reverse($reference, $amountMinor)
            : $this->settle($reference, $amountMinor);

        $event->update(['status' => $outcome === 'unmatched' ? 'unmatched' : 'processed', 'processed_at' => now()]);

        return $outcome;
    }

    /** Credit path: confirm a wallet funding or activate a subscription. */
    private function settle(?string $reference, ?int $amountMinor): string
    {
        if (! $reference) {
            return 'unmatched';
        }

        if ($funding = $this->findFunding($reference)) {
            $this->settleFunding($funding, $amountMinor);

            return 'funded';
        }

        if ($subscription = $this->findSubscription($reference)) {
            $this->activateSubscription($subscription);

            return 'subscription_active';
        }

        return 'unmatched';
    }

    /** Reversal path (refund/chargeback): claw back a funding or cancel a subscription. */
    private function reverse(?string $reference, ?int $amountMinor): string
    {
        if (! $reference) {
            return 'unmatched';
        }

        if ($funding = $this->findFunding($reference)) {
            $this->reverseFunding($funding, $amountMinor);

            return 'reversed';
        }

        if ($subscription = $this->findSubscription($reference)) {
            $this->cancelSubscription($subscription);

            return 'reversed';
        }

        return 'unmatched';
    }

    /**
     * Correlate a webhook to a funding row by our reference (gateway_ref) or the
     * gateway's own transaction id (gateway_txn_ref) — the latter is how gateways
     * whose refund webhooks omit our reference (e.g. Monnify) correlate back.
     */
    private function findFunding(string $reference): ?WalletFundingTransaction
    {
        return WalletFundingTransaction::where('gateway_ref', $reference)
            ->orWhere('gateway_txn_ref', $reference)
            ->first();
    }

    /**
     * Correlate a webhook to a subscription by our `sub_<id>` reference or, when the
     * gateway omits it on refunds (e.g. Monnify), by the stored gateway_txn_ref.
     */
    private function findSubscription(string $reference): ?Subscription
    {
        if (str_starts_with($reference, 'sub_')) {
            return Subscription::find((int) substr($reference, 4));
        }

        return Subscription::where('gateway_txn_ref', $reference)->first();
    }

    private function settleFunding(WalletFundingTransaction $funding, ?int $amountMinor): void
    {
        if ($funding->status === 'success') {
            return; // already settled
        }

        $funding->update(['status' => 'success']);
        $this->wallets->creditCurrency($funding->wallet, $amountMinor ?? $funding->amount_minor);
    }

    private function reverseFunding(WalletFundingTransaction $funding, ?int $amountMinor): void
    {
        if ($funding->status !== 'success') {
            return; // nothing settled to claw back (pending/failed/already refunded)
        }

        $reversed = $amountMinor ?? $funding->amount_minor;
        $funding->update(['status' => 'refunded']);
        $this->wallets->debitCurrency($funding->wallet, $reversed);

        $this->audit->record('funding.refunded', $funding, ['status' => 'success'], ['status' => 'refunded', 'amount_minor' => $reversed]);
    }

    private function cancelSubscription(Subscription $subscription): void
    {
        if ($subscription->status === 'refunded') {
            return;
        }

        $subscription->update(['status' => 'refunded', 'renews_at' => null]);

        $this->audit->record('subscription.refunded', $subscription, [], ['status' => 'refunded']);

        // Unwind any referral commission this subscription earned (FR-7.3): an
        // in-escrow chargeback cancels the commission rather than letting it clear.
        $this->referrals->reverseForSubscription($subscription);
    }

    private function activateSubscription(Subscription $subscription): void
    {
        if ($subscription->status === 'active') {
            return;
        }

        $subscription->update([
            'status' => 'active',
            'started_at' => now(),
            'renews_at' => $this->renewsAt($subscription->plan),
        ]);

        // Verified-payment gate (FR-7.2): qualify any pending referral for this payer.
        $subscriber = $subscription->subscriber;
        if ($subscriber instanceof User) {
            $this->referrals->qualifyForSubscriber($subscriber, $subscription);
            $subscriber->notify(new SubscriptionActivated($subscription)); // receipt
        }
    }

    private function renewsAt(?Plan $plan): Carbon
    {
        return match ($plan?->interval) {
            'year' => now()->addYear(),
            'quarter' => now()->addMonths(3),
            'term' => now()->addMonths(4),
            'week' => now()->addWeek(),
            default => now()->addMonth(),
        };
    }
}
