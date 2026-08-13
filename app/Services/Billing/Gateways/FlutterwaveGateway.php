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

    public function verify(string $reference): GatewayTransactionStatus
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->get(rtrim($this->baseUrl, '/').'/transactions/verify_by_reference', ['tx_ref' => $reference]);

        // A reference the gateway has never seen a checkout for (e.g. one only
        // ever created locally, never initialized) 404s — not paid, not a fault.
        if ($response->status() === 404) {
            return new GatewayTransactionStatus('pending');
        }

        $decoded = $response->throw()->json();
        $data = $decoded['data'] ?? [];
        $status = match ($data['status'] ?? null) {
            'successful' => 'success',
            'failed' => 'failed',
            default => 'pending',
        };

        $amountMinor = isset($data['amount']) ? (int) round(((float) $data['amount']) * 100) : null;

        return new GatewayTransactionStatus($status, $amountMinor, is_array($decoded) ? $decoded : []);
    }
}
