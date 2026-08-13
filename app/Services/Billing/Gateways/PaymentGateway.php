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

    /**
     * Poll the gateway directly for a transaction's current state — used to
     * reconcile a still-`pending` subscription synchronously (e.g. the webhook
     * hasn't arrived yet, or can't reach this environment) instead of only
     * ever waiting passively for the webhook.
     *
     * @param  string  $reference  the same reference passed to initialize()
     */
    public function verify(string $reference): GatewayTransactionStatus;
}
