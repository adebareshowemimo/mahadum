<?php

namespace App\Services\Billing\Gateways;

/**
 * Result of initialising a hosted checkout with a payment gateway. The client
 * opens `checkoutUrl` (null when no live gateway is configured); settlement
 * arrives later via the signed webhook, correlated by `reference`.
 *
 * `providerReference` is the gateway's OWN id for the transaction, when it differs
 * from our reference and is needed to correlate later events. Monnify sets it
 * (its refund webhook only carries the gateway's transactionReference, not our
 * paymentReference); Paystack/Flutterwave echo our reference back, so they leave
 * it null.
 */
final class GatewayCheckout
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $reference,
        public readonly ?string $checkoutUrl,
        public readonly array $raw = [],
        public readonly ?string $providerReference = null,
    ) {}
}
