<?php

return [
    /*
     * Public CDN base for media URLs (falls back to the app URL).
     */
    'cdn_base' => env('CDN_BASE', env('APP_URL', 'http://localhost')),

    /*
     * Client-facing feature flags surfaced by GET /api/v1/config.
     */
    'features' => [
        'telco_billing' => (bool) env('FEATURE_TELCO_BILLING', false),
        'ai_pronunciation' => (bool) env('FEATURE_AI_PRONUNCIATION', false),
    ],
];
