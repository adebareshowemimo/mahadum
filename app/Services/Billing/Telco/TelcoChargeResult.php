<?php

namespace App\Services\Billing\Telco;

/**
 * Outcome of an airtime (VAS) charge attempt against the operator SDP.
 * `status` is normalised to success | insufficient | error; `operatorRef` is the
 * operator's own reference when supplied (used to correlate the async DLR webhook).
 */
final class TelcoChargeResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $operatorRef = null,
        public readonly array $raw = [],
    ) {}
}
