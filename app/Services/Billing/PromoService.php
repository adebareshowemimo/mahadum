<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\Subscription;
use App\Models\User;

/**
 * Validates and applies promo codes at consumer checkout. Rules (FR / BRD):
 * single-use per user, no stacking, no retro, honours tier restriction, validity
 * window, and the global redemption cap. Discount is computed off the plan price.
 */
class PromoService
{
    /** Thrown reason codes → human messages surfaced to the client. */
    public const REASONS = [
        'not_found' => 'That promo code isn’t valid.',
        'inactive' => 'That promo code is no longer active.',
        'not_started' => 'That promo code isn’t active yet.',
        'expired' => 'That promo code has expired.',
        'wrong_tier' => 'That promo code doesn’t apply to this plan.',
        'exhausted' => 'That promo code has reached its redemption limit.',
        'already_used' => 'You’ve already used that promo code.',
    ];

    /**
     * Resolve a code for a plan/user. Returns [PromoCode, discountMinor, finalMinor]
     * or throws PromoException with a reason key.
     */
    public function evaluate(string $code, Plan $plan, User $user): PromoOutcome
    {
        $promo = PromoCode::whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])->first();

        if ($promo === null) {
            throw new PromoException('not_found');
        }
        if ($promo->status !== 'active') {
            throw new PromoException('inactive');
        }
        if ($promo->valid_from && now()->lt($promo->valid_from)) {
            throw new PromoException('not_started');
        }
        if ($promo->valid_to && now()->gt($promo->valid_to)) {
            throw new PromoException('expired');
        }
        if ($promo->applicable_tier && $promo->applicable_tier !== $plan->code) {
            throw new PromoException('wrong_tier');
        }
        if ($promo->max_redemptions !== null && $promo->redeemed_count >= $promo->max_redemptions) {
            throw new PromoException('exhausted');
        }
        if (PromoRedemption::where('promo_code_id', $promo->id)->where('user_id', $user->id)->exists()) {
            throw new PromoException('already_used');
        }

        $price = (int) $plan->price_minor;
        $discount = $promo->discount_type === 'percent'
            ? (int) floor($price * min(100, max(0, $promo->value)) / 100)
            : min($price, max(0, $promo->value));

        return new PromoOutcome($promo, $discount, max(0, $price - $discount));
    }

    /** Record a consumer redemption and advance the code's counter. */
    public function redeem(PromoCode $promo, User $user, Subscription $subscription): void
    {
        PromoRedemption::create([
            'promo_code_id' => $promo->id,
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);

        $promo->increment('redeemed_count');
    }
}
