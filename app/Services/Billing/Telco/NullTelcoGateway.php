<?php

namespace App\Services\Billing\Telco;

/**
 * No-op SDP used when no live operator gateway is configured (local/CI). Charges
 * succeed deterministically (so the daily engine can be exercised without a real
 * operator) and OTP delivery is a no-op — the code still lands in the logs via
 * TelcoOtpService for local debugging.
 */
class NullTelcoGateway implements TelcoGateway
{
    public function charge(string $msisdn, string $operator, int $amountMinor, string $reference): TelcoChargeResult
    {
        return new TelcoChargeResult('success', $reference);
    }

    public function sendOtp(string $msisdn, string $operator, string $code): void
    {
        // no-op
    }
}
