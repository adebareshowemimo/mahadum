<?php

namespace App\Services\Referral;

use App\Models\Commission;
use App\Models\Organization;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Referral lifecycle: issue a code, attribute a sign-up (with fraud guards), and
 * qualify on a verified payment by creating an escrowed commission.
 *
 * Fraud guards: self-referral block, device-fingerprint reuse block (FR-7.1),
 * and code freeze (FlagReferralVelocity / FR-7.5). The verified-payment gate
 * (FR-7.2) is enforced by only qualifying from PaymentService on activation.
 */
class ReferralService
{
    /**
     * @param  User|Organization  $owner  ReferralCode.owner is polymorphic — a
     *                                    personal code (kind 'user') or a school's own code (kind 'org').
     */
    public function codeFor(Model $owner): ReferralCode
    {
        return ReferralCode::firstOrCreate(
            ['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'kind' => $owner instanceof Organization ? 'org' : 'user'],
            ['code' => $this->uniqueCode(), 'status' => 'active'],
        );
    }

    /**
     * Record a sign-up against a referral code. Returns the Referral, or null if
     * the code is unknown/inactive or it's a self-referral. Device reuse is
     * recorded as `rejected` (kept for audit), not silently dropped.
     */
    public function attribute(User $referred, ?string $code, ?string $fingerprint): ?Referral
    {
        if (! $code) {
            return null;
        }

        $referralCode = ReferralCode::where('code', $code)->where('status', 'active')->first();

        if (! $referralCode) {
            return null;
        }

        // Self-referral guard.
        if ($referralCode->owner_type === $referred->getMorphClass() && (int) $referralCode->owner_id === (int) $referred->id) {
            return null;
        }

        // FR-7.1: same device already used for a referral → fraud.
        $deviceReused = $fingerprint && Referral::where('device_fingerprint', $fingerprint)->exists();

        return Referral::create([
            'referral_code_id' => $referralCode->id,
            'referred_user_id' => $referred->id,
            'device_fingerprint' => $fingerprint,
            'status' => $deviceReused ? 'rejected' : 'pending',
            'signed_up_at' => now(),
        ]);
    }

    /**
     * Verified-payment gate: when a referred user's subscription activates, mark
     * the referral qualified and create a pending_escrow commission for the
     * code owner. Idempotent and skipped for free plans.
     */
    public function qualifyForSubscriber(User $user, Subscription $subscription): ?Commission
    {
        $referral = Referral::where('referred_user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('referralCode', fn ($q) => $q->where('status', 'active'))
            ->with('referralCode')
            ->first();

        if (! $referral || $referral->commissions()->exists()) {
            return null;
        }

        $price = (int) $subscription->plan->price_minor;
        $amount = (int) round($price * config('referral.rate'));

        $referral->update(['status' => 'qualified', 'referred_subscription_id' => $subscription->id]);

        if ($amount < 1) {
            return null; // free plan → no commission, but referral still qualified
        }

        $owner = $referral->referralCode->owner;

        $commission = new Commission([
            'amount_minor' => $amount,
            'status' => 'pending_escrow',
            'escrow_until' => now()->addDays(config('referral.escrow_days')),
        ]);
        $commission->referral()->associate($referral);
        $commission->beneficiary()->associate($owner);
        $commission->save();

        return $commission;
    }

    /**
     * Refund/chargeback clawback (FR-7.3): when a qualifying subscription is
     * refunded, unwind the commission it generated. A still-escrowed commission
     * is voided outright (the money never left escrow); an already-cleared one
     * is flagged `clawback_pending` for finance to recover downstream. Idempotent
     * — re-running leaves already-unwound rows untouched.
     */
    public function reverseForSubscription(Subscription $subscription): void
    {
        $referral = Referral::where('referred_subscription_id', $subscription->id)
            ->with('commissions')
            ->first();

        if (! $referral) {
            return;
        }

        foreach ($referral->commissions as $commission) {
            $reversedStatus = match ($commission->status) {
                'pending_escrow' => 'reversed',
                'cleared' => 'clawback_pending',
                default => null, // already reversed / clawback_pending → leave as-is
            };

            if ($reversedStatus !== null) {
                $commission->update(['status' => $reversedStatus]);
            }
        }

        if ($referral->status === 'qualified') {
            $referral->update(['status' => 'reversed']);
        }
    }

    private function uniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (ReferralCode::where('code', $code)->exists());

        return $code;
    }
}
