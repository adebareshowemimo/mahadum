<?php

return [
    /*
     * Commission as a fraction of the qualifying subscription's plan price.
     * No commission is created for free (price 0) plans.
     */
    'rate' => (float) env('REFERRAL_RATE', 0.20),

    /*
     * Commission escrow window (FR-7.3 / Rule 9): a chargeback inside this
     * window cancels the commission; after it, ClearEscrowedCommissions clears it.
     */
    'escrow_days' => (int) env('REFERRAL_ESCROW_DAYS', 14),

    /*
     * Velocity guard (FR-7.5): more than this many sign-ups on one code within
     * 24h flags + freezes the code.
     */
    'velocity_limit' => (int) env('REFERRAL_VELOCITY_LIMIT', 15),
];
