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
                'referral.commission_bps' => [
                    'label' => 'Referral commission (basis points)',
                    'help' => 'What the referrer earns on each qualifying purchase the referred person makes inside the earning window. 500 = 5%, 1000 = 10%.',
                    'type' => 'int',
                    'min' => 0,
                    'max' => 10_000,
                    'default' => (int) env('REFERRAL_COMMISSION_BPS', 500),
                ],
                'referral.earning_window_days' => [
                    'label' => 'Earning window (days)',
                    'help' => 'Days after a referral activates during which the referred person\'s purchases still earn the referrer a commission.',
                    'type' => 'int',
                    'min' => 1,
                    'max' => 365,
                    'default' => (int) env('REFERRAL_EARNING_WINDOW_DAYS', 30),
                ],
                'referral.escrow_days' => [
                    'label' => 'Commission escrow (days)',
                    'help' => 'Days a new commission is held before it can be paid out. A chargeback inside this window cancels it.',
                    'type' => 'int',
                    'min' => 0,
                    'max' => 90,
                    'default' => (int) env('REFERRAL_ESCROW_DAYS', 14),
                ],
                'referral.velocity_limit' => [
                    'label' => 'Sign-up velocity limit (per 24h)',
                    'help' => 'Sign-ups on one referral code within 24 hours before it is auto-frozen for fraud review.',
                    'type' => 'int',
                    'min' => 1,
                    'max' => 500,
                    'default' => (int) env('REFERRAL_VELOCITY_LIMIT', 15),
                ],
                'referral.activation_min_lessons' => [
                    'label' => 'Activation: lessons required',
                    'help' => 'Lessons the referred learner must finish before the code activates. 0 disables the lesson gate.',
                    'type' => 'int',
                    'min' => 0,
                    'max' => 20,
                    'default' => (int) env('REFERRAL_ACTIVATION_MIN_LESSONS', 1),
                ],
                'referral.activation_min_quizzes' => [
                    'label' => 'Activation: quizzes required',
                    'help' => 'Quizzes the referred learner must finish before the code activates. 0 disables the quiz gate.',
                    'type' => 'int',
                    'min' => 0,
                    'max' => 20,
                    'default' => (int) env('REFERRAL_ACTIVATION_MIN_QUIZZES', 1),
                ],
                'referral.activation_requires_paid_subscription' => [
                    'label' => 'Activation: require a paid subscription',
                    'help' => 'When on, the referred person must hold a paid subscription for the referral code to activate.',
                    'type' => 'bool',
                    'default' => (bool) env('REFERRAL_ACTIVATION_REQUIRES_PAID_SUBSCRIPTION', true),
                ],
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
        'teacher_compensation' => [
            'label' => 'Teacher compensation',
            'settings' => [
                'teacher_compensation.rate_per_student_minor' => [
                    'label' => 'Rate per paying student (₦, minor units)',
                    'help' => 'Monthly amount a teacher accrues per currently-enrolled student whose school has an active/paid seat allocation. 0 disables accrual. 20000 = ₦200.',
                    'type' => 'int',
                    'min' => 0,
                    'default' => 0,
                ],
            ],
        ],

        'learning_rewards' => [
            'label' => 'Learning rewards',
            'settings' => [
                'learning.quiz_completion_xp' => [
                    'label' => 'Quiz completion XP',
                    'help' => 'XP awarded for completing a quiz. Saving this value updates existing quizzes and becomes the default for new quizzes.',
                    'type' => 'int',
                    'min' => 0,
                    'max' => 1000,
                    'default' => (int) env('QUIZ_COMPLETION_XP', 1),
                ],
                'learning.video_completion_xp' => [
                    'label' => 'Video completion XP',
                    'help' => 'XP awarded for completing a video. Saving this value updates existing videos and becomes the default for new videos.',
                    'type' => 'int',
                    'min' => 0,
                    'max' => 1000,
                    'default' => (int) env('VIDEO_COMPLETION_XP', 5),
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
        'email' => [
            'label' => 'Email',
            'settings' => [
                'email.log_retention_days' => [
                    'label' => 'Email-log retention (days)',
                    'help' => 'Delete email-log rows older than this. Keeps only what compliance needs; 0 disables pruning.',
                    'type' => 'int',
                    'min' => 0,
                    'max' => 3650,
                    'default' => (int) env('EMAIL_LOG_RETENTION_DAYS', 365),
                ],
            ],
        ],
    ],
];
