<?php

return [
    'shell' => env('SEO_SHELL_PATH', base_path('resources/spa/index.html')),
    'prerender' => env('SEO_PRERENDER_PATH', public_path('prerender')),
    // Set only after the domain property is verified in each service.
    'google_verification' => env('GOOGLE_SITE_VERIFICATION'),
    'bing_verification' => env('BING_SITE_VERIFICATION'),
];
