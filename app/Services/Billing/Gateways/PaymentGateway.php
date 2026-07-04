<?php

namespace App\Services\Billing\Gateways;

/**
 * A payment gateway that can open a hosted checkout for a given reference.
 * Implementations are swappable (Paystack, Flutterwave, …) and resolved by
 * PaymentGatewayManager; the inbound webhook (PaymentService) is gateway-agnostic.
 */
interface PaymentGateway
{
    /**
     * @param  string  $reference  our correlation key (e.g. a funding UUID or `sub_<id>`)
     * @param  int  $amountMinor  amount in minor units (kobo)
     * @param  array<string, mixed>  $metadata
     */
    public function initialize(string $reference, int $amountMinor, string $email, array $metadata = []): GatewayCheckout;
}
