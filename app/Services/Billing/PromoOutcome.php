<?php

namespace App\Services\Billing;

use App\Models\PromoCode;

/** Result of evaluating a promo code against a plan. */
final class PromoOutcome
{
    public function __construct(
        public readonly PromoCode $promo,
        public readonly int $discountMinor,
        public readonly int $finalMinor,
    ) {}
}
