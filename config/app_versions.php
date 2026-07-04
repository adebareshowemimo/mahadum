<?php

/**
 * Minimum supported client versions for the force-update gate (MinAppVersion
 * middleware). Bump these to require an app update; surfaced via GET /api/v1/config.
 */
return [
    'min' => [
        'ios' => env('MIN_APP_VERSION_IOS', '1.0.0'),
        'android' => env('MIN_APP_VERSION_ANDROID', '1.0.0'),
    ],
];
