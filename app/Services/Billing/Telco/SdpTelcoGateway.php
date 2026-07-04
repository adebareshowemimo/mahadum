<?php

namespace App\Services\Billing\Telco;

use Illuminate\Support\Facades\Http;

/**
 * HTTP client for an operator SDP aggregator. Charge is best-effort — a declined
 * or failed charge is a normal business outcome (→ grace), never an exception —
 * so the daily engine keeps processing the rest of the batch. The exact payload
 * shape varies per aggregator; adjust the field mapping to the contracted API.
 */
class SdpTelcoGateway implements TelcoGateway
{
    public function __construct(private string $baseUrl, private string $token) {}

    public function charge(string $msisdn, string $operator, int $amountMinor, string $reference): TelcoChargeResult
    {
        $response = Http::withToken($this->token)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/charge', [
                'msisdn' => $msisdn,
                'operator' => $operator,
                'amount' => $amountMinor,
                'reference' => $reference,
            ]);

        if ($response->failed()) {
            return new TelcoChargeResult('error', null, (array) $response->json());
        }

        $body = (array) $response->json();
        $status = match ($body['status'] ?? 'error') {
            'success' => 'success',
            'insufficient', 'insufficient_funds' => 'insufficient',
            default => 'error',
        };

        return new TelcoChargeResult($status, $body['operator_ref'] ?? null, $body);
    }

    public function sendOtp(string $msisdn, string $operator, string $code): void
    {
        // Best-effort delivery; failure doesn't block enrolment (the user can resend).
        Http::withToken($this->token)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/otp', [
                'msisdn' => $msisdn,
                'operator' => $operator,
                'message' => "Your Mahadum.360 verification code is {$code}.",
            ]);
    }
}
