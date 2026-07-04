<?php

/*
 * Canonical brand identity for server-rendered surfaces (emails, PDFs).
 * Mirrors web/src/lib/brand.ts — keep the wordmark + tagline in sync. Colours
 * echo the "Gilded Adire" identity (gold on ink). Overridable via env so a
 * white-label or a changed logo needs no redeploy.
 */

return [
    'name' => env('BRAND_NAME', 'MAHADUM.360'),

    // Locked tagline (2026-06-27).
    'tagline' => env('BRAND_TAGLINE', 'Learn the language. Live the culture. Connect the generations.'),

    // Absolute URL to a *raster* logo (email clients don't render SVG). Unset by
    // default → the header falls back to the gold wordmark. Set BRAND_LOGO_URL to
    // a hosted PNG in production.
    'logo_url' => env('BRAND_LOGO_URL'),

    // Where "visit the app" CTAs point (the web SPA).
    'url' => rtrim((string) env('APP_FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

    // Shown in the email footer + as the reply-to / support contact.
    'support_email' => env('BRAND_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', 'support@mahadum360.com')),

    // Postal address in the footer (CAN-SPAM / good-practice for marketing mail).
    'address' => env('BRAND_ADDRESS', 'Lagos, Nigeria'),

    'colors' => [
        'gold' => env('BRAND_COLOR_GOLD', '#C7952B'),
        'ink' => env('BRAND_COLOR_INK', '#1E1B16'),
    ],
];
