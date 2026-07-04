<?php

namespace App\Services\Billing;

use App\Models\TelcoOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Issues and verifies one-time codes that gate airtime (VAS) enrolment. The
 * caller must prove control of the MSISDN — request a code, confirm it, then
 * subscribe — so a stolen session can't silently bill an arbitrary number.
 *
 * Codes are stored hashed, expire fast, cap verify attempts, and are single-use.
 */
class TelcoOtpService
{
    private const TTL_MINUTES = 5;

    private const MAX_ATTEMPTS = 5;

    /** A verified code stays usable for enrolment this long. */
    private const VERIFICATION_WINDOW_MINUTES = 15;

    public function __construct(private TelcoGatewayManager $gateways) {}

    /** Issue a fresh code for $user+$msisdn, invalidating any prior live one. */
    public function request(User $user, string $msisdn, string $operator): TelcoOtp
    {
        TelcoOtp::where('user_id', $user->id)
            ->where('msisdn', $msisdn)
            ->whereNull('consumed_at')
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = TelcoOtp::create([
            'user_id' => $user->id,
            'msisdn' => $msisdn,
            'operator' => $operator,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        // Hand the code to the operator SDP for SMS delivery (no-op off-live).
        $this->gateways->driver()->sendOtp($msisdn, $operator, $code);

        // The code is never returned in the API response; it only surfaces in
        // logs for local/test debugging.
        Log::info('telco.otp.issued', ['otp_id' => $otp->id, 'operator' => $operator, 'msisdn' => $this->mask($msisdn)]);

        if (app()->environment('local', 'testing')) {
            Log::debug('telco.otp.code', ['otp_id' => $otp->id, 'code' => $code]);
        }

        return $otp;
    }

    /** Confirm a code. Returns false on no live OTP, too many attempts, or mismatch. */
    public function verify(User $user, string $msisdn, string $code): bool
    {
        $otp = TelcoOtp::where('user_id', $user->id)
            ->where('msisdn', $msisdn)
            ->whereNull('consumed_at')
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            return false;
        }

        $otp->update(['verified_at' => now()]);

        return true;
    }

    /**
     * Consume a still-valid verification so the MSISDN can be enrolled exactly
     * once. Returns false when there is no fresh verified code to spend.
     */
    public function consumeVerified(User $user, string $msisdn): bool
    {
        $otp = TelcoOtp::where('user_id', $user->id)
            ->where('msisdn', $msisdn)
            ->whereNull('consumed_at')
            ->whereNotNull('verified_at')
            ->where('verified_at', '>', now()->subMinutes(self::VERIFICATION_WINDOW_MINUTES))
            ->latest()
            ->first();

        if (! $otp) {
            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }

    private function mask(string $msisdn): string
    {
        return strlen($msisdn) > 4
            ? str_repeat('*', strlen($msisdn) - 4).substr($msisdn, -4)
            : $msisdn;
    }
}
