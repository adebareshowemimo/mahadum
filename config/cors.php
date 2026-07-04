<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Scoped to the API surface and the Sanctum CSRF-cookie endpoint so the
    | first-party web SPA can authenticate with cookies. `supports_credentials`
    | MUST be true for the browser to send/receive the session + XSRF cookies,
    | and origins must be listed explicitly (a wildcard is invalid with
    | credentials). Mobile / bearer-token clients are unaffected by CORS.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // SPA origins, comma-separated, e.g. https://app.mahadum360.com,http://localhost:3000
    'allowed_origins' => array_filter(explode(',', (string) env(
        'FRONTEND_URLS',
        env('FRONTEND_URL', 'http://localhost:3000,http://localhost:5173'),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
