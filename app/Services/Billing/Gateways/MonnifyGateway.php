<?php

namespace App\Services\Billing\Gateways;

use Illuminate\Support\Facades\Http;

/**
 * Monnify hosted checkout. Unlike Paystack/Flutterwave this is a two-step flow:
 * authenticate (Basic apiKey:secretKey → short-lived bearer token) then
 * init-transaction. Amounts are sent in major units (Naira); our `reference` is
 * passed as `paymentReference` so the inbound webhook correlates back.
 *
 * @see https://developers.monnify.com/api
 */
class MonnifyGateway implements PaymentGateway
{
    public function __construct(
        private string $apiKey,
        private string $secret,
        private string $contractCode,
        private string $baseUrl,
    ) {}

    public function initialize(string $reference, int $amountMinor, string $email, array $metadata = []): GatewayCheckout
    {
        $base = rtrim($this->baseUrl, '/');

        // Step 1 — exchange the API key + secret for a short-lived access token.
        $token = Http::withBasicAuth($this->apiKey, $this->secret)
            ->acceptJson()
            ->post($base.'/api/v1/auth/login')
            ->throw()
            ->json('responseBody.accessToken');

        // Step 2 — initialise the transaction; Monnify returns the hosted checkout URL.
        $response = Http::withToken((string) $token)
            ->acceptJson()
            ->post($base.'/api/v1/merchant/transactions/init-transaction', [
                'amount' => round($amountMinor / 100, 2),   // major units (Naira)
                'customerEmail' => $email,
                'customerName' => $email,
                'paymentReference' => $reference,
                'paymentDescription' => 'MAHADUM.360 payment',
                'currencyCode' => 'NGN',
                'contractCode' => $this->contractCode,
                'redirectUrl' => (string) config('app.url'),
                'metaData' => $metadata,
            ])
            ->throw()
            ->json();

        return new GatewayCheckout(
            $reference,
            $response['responseBody']['checkoutUrl'] ?? null,
            is_array($response) ? $response : [],
            // Monnify's refund webhook only carries this, not our paymentReference.
            $response['responseBody']['transactionReference'] ?? null,
        );
    }
}
