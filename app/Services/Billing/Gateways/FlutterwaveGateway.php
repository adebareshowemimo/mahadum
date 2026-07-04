<?php

namespace App\Services\Billing\Gateways;

use Illuminate\Support\Facades\Http;

/**
 * Flutterwave Standard hosted checkout. Amounts are sent in major units, and our
 * `reference` is passed as `tx_ref` so the inbound webhook correlates back.
 *
 * @see https://developer.flutterwave.com/reference/endpoints/standard
 */
class FlutterwaveGateway implements PaymentGateway
{
    public function __construct(private string $secret, private string $baseUrl) {}

    public function initialize(string $reference, int $amountMinor, string $email, array $metadata = []): GatewayCheckout
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/payments', [
                'tx_ref' => $reference,
                'amount' => round($amountMinor / 100, 2),
                'currency' => 'NGN',
                'customer' => ['email' => $email],
                'meta' => $metadata,
            ])
            ->throw()
            ->json();

        return new GatewayCheckout(
            $reference,
            $response['data']['link'] ?? null,
            is_array($response) ? $response : [],
        );
    }
}
