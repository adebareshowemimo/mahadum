<?php

namespace App\Services\Billing\Gateways;

/**
 * No-op gateway used when no live gateway is configured (local/CI). It records
 * nothing and returns a null checkout URL — the pending transaction still exists
 * and can be settled by a (manually replayed) webhook.
 */
class NullGateway implements PaymentGateway
{
    public function initialize(string $reference, int $amountMinor, string $email, array $metadata = []): GatewayCheckout
    {
        return new GatewayCheckout($reference, null);
    }
}
