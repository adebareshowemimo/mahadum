<?php

/*
 * Referral defaults. These are the fallback values only — the live values are
 * the admin-editable platform settings in config/settings.php (the "referrals"
 * group), read through App\Services\Settings. Change behaviour there, not here.
 */
return [
    /*
     * Commission the referrer earns on each qualifying purchase the referred
     * person makes within the earning window, in basis points (500 = 5%).
     */
    'commission_bps' => (int) env('REFERRAL_COMMISSION_BPS', 500),

    /*
     * Days after a referral activates during which the referred person's
     * purchases still earn the referrer a commission.
     */
    'earning_window_days' => (int) env('REFERRAL_EARNING_WINDOW_DAYS', 30),

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

    /*
     * Activation gate: the referred person must hold a paid subscription and
     * have finished this many lessons and quizzes before the code activates.
     */
    'activation_min_lessons' => (int) env('REFERRAL_ACTIVATION_MIN_LESSONS', 1),
    'activation_min_quizzes' => (int) env('REFERRAL_ACTIVATION_MIN_QUIZZES', 1),
    'activation_requires_paid_subscription' => (bool) env('REFERRAL_ACTIVATION_REQUIRES_PAID_SUBSCRIPTION', true),
];
