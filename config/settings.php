<?php

/*
 * Whitelist of admin-editable platform settings. Each entry defines its display
 * metadata + type + default (sourced from env/config). The Settings service
 * returns the DB override if one exists, else this default — so nothing here can
 * be edited to an unknown key, and an un-set setting always has a safe value.
 *
 * Keys are grouped only for display; the key string is global and unique.
 */
return [
    'groups' => [
        'compliance' => [
            'label' => 'Compliance',
            'settings' => [
                'compliance.minor_age' => [
                    'label' => 'Digital consent age',
                    'help' => 'Age (years) below which a learner needs verifiable parental consent (COPPA/NDPA). Drives the sign-up age gate.',
                    'type' => 'int',
                    'min' => 4,
                    'max' => 18,
                    'default' => (int) env('COMPLIANCE_MINOR_AGE', 13),
                ],
            ],
        ],
        'referrals' => [
            'label' => 'Referrals & payouts',
            'settings' => [
                'referral.payout_floor_minor' => [
                    'label' => 'Payout floor (₦, minor units)',
                    'help' => 'Minimum a beneficiary can request in one payout. 500000 = ₦5,000.',
                    'type' => 'int',
                    'min' => 0,
                    'default' => 500_000,
                ],
                'referral.payout_cap_minor' => [
                    'label' => 'Monthly payout cap (₦, minor units)',
                    'help' => 'Maximum an individual can be paid out per month. 5000000 = ₦50,000.',
                    'type' => 'int',
                    'min' => 0,
                    'default' => 5_000_000,
                ],
            ],
        ],
        'features' => [
            'label' => 'Feature flags',
            'settings' => [
                'feature.telco_billing' => [
                    'label' => 'Telco airtime billing',
                    'help' => 'Surface the "pay with airtime" option (client feature flag).',
                    'type' => 'bool',
                    'default' => (bool) env('FEATURE_TELCO_BILLING', false),
                ],
                'feature.ai_pronunciation' => [
                    'label' => 'AI pronunciation coach',
                    'help' => 'Enable the AI pronunciation-scoring feature flag.',
                    'type' => 'bool',
                    'default' => (bool) env('FEATURE_AI_PRONUNCIATION', false),
                ],
            ],
        ],
    ],
];
