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

    public function verify(string $reference): GatewayTransactionStatus
    {
        $response = Http::withToken($this->secret)
            ->acceptJson()
            ->get(rtrim($this->baseUrl, '/').'/transaction/verify/'.rawurlencode($reference));

        // A reference the gateway has never seen a checkout for (e.g. one only
        // ever created locally, never initialized) 404s — not paid, not a fault.
        if ($response->status() === 404) {
            return new GatewayTransactionStatus('pending');
        }

        $decoded = $response->throw()->json();
        $data = $decoded['data'] ?? [];
        $status = match ($data['status'] ?? null) {
            'success' => 'success',
            'failed', 'abandoned' => 'failed',
            default => 'pending',
        };

        $amountMinor = isset($data['amount']) ? (int) $data['amount'] : null;

        return new GatewayTransactionStatus($status, $amountMinor, is_array($decoded) ? $decoded : []);
    }
}
