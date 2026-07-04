<?php

namespace App\Services\Billing\Gateways;

use Illuminate\Support\Facades\Http;

/**
 * Paystack hosted checkout. Amounts are sent in kobo (minor units), and our
 * `reference` is passed through verbatim so the inbound `charge.success` webhook
 * correlates back to the originating funding/subscription.
 *
 * @see https://paystack.com/docs/api/transaction/#initialize
 */
class PaystackGateway implements PaymentGateway
{
    public function __construct(private string $secret, private string $baseUrl) {}

    public function initialize(string $reference, int $amountMinor, string $email, array $metadata = []): GatewayCheckout
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/transaction/initialize', [
                'reference' => $reference,
                'amount' => $amountMinor,
                'email' => $email,
                'currency' => 'NGN',
                'metadata' => $metadata,
            ])
            ->throw()
            ->json();

        return new GatewayCheckout(
            $reference,
            $response['data']['authorization_url'] ?? null,
            is_array($response) ? $response : [],
        );
    }
}
