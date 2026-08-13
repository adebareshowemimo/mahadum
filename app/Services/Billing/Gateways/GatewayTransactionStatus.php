<?php

namespace App\Services\Billing\Gateways;

/**
 * Result of polling a gateway for a transaction's current state — the
 * synchronous counterpart to the inbound webhook, used when a subscriber
 * checks on a still-`pending` subscription instead of waiting for the
 * webhook to arrive (e.g. a local dev environment the gateway can't reach).
 */
final class GatewayTransactionStatus
{
    /**
     * @param  'success'|'pending'|'failed'  $status
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $status,
        public readonly ?int $amountMinor = null,
        public readonly array $raw = [],
    ) {}
}
