<?php

namespace App\Services\Billing;

use RuntimeException;

/** A promo code failed validation; `reason` maps to PromoService::REASONS. */
class PromoException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(PromoService::REASONS[$reason] ?? 'That promo code isn’t valid.');
    }
}
