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
        $token = $this->authenticate($base);

        // Step 2 — initialise the transaction; Monnify returns the hosted checkout URL.
        $response = Http::withToken($token)
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

    public function verify(string $reference): GatewayTransactionStatus
    {
        $base = rtrim($this->baseUrl, '/');
        $token = $this->authenticate($base);

        $response = Http::withToken($token)
            ->acceptJson()
            ->get($base.'/api/v1/merchant/transactions/query', ['paymentReference' => $reference]);

        // A reference the gateway has never seen a checkout for (e.g. one only
        // ever created locally, never initialized) 404s — not paid, not a fault.
        if ($response->status() === 404) {
            return new GatewayTransactionStatus('pending');
        }

        $decoded = $response->throw()->json();
        $body = $decoded['responseBody'] ?? [];
        $status = match ($body['paymentStatus'] ?? null) {
            'PAID' => 'success',
            'FAILED', 'EXPIRED', 'CANCELLED' => 'failed',
            default => 'pending',
        };

        $amountMinor = isset($body['amountPaid'])
            ? (int) round(((float) $body['amountPaid']) * 100)
            : null;

        return new GatewayTransactionStatus($status, $amountMinor, is_array($decoded) ? $decoded : []);
    }

    /** Step 1 — exchange the API key + secret for a short-lived access token. */
    private function authenticate(string $base): string
    {
        return (string) Http::withBasicAuth($this->apiKey, $this->secret)
            ->acceptJson()
            ->post($base.'/api/v1/auth/login')
            ->throw()
            ->json('responseBody.accessToken');
    }
}
