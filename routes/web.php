<?php

use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

// One-click marketing unsubscribe (signed link embedded in campaign emails).
Route::get('/email/unsubscribe/{email}', UnsubscribeController::class)
    ->middleware('signed')
    ->name('email.unsubscribe');

// Serve the built React SPA for every route except the API, Sanctum, storage,
// and health-check paths. The deploy script builds `web/` and copies its
// index.html to resources/spa/index.html; client-side routing (React Router)
// takes over from there. Keep this LAST — routes above must match first.
Route::get('/{any?}', function () {
    $index = base_path('resources/spa/index.html');

    abort_unless(file_exists($index), 404, 'SPA build not found — run the deploy script to build web/ first.');

    $response = response()->file($index, [
        'Content-Type' => 'text/html',
        // The HTML points at versioned Vite assets. Revalidate it on every
        // navigation so a production deployment cannot strand users on an
        // older bundle while the hashed assets themselves remain cacheable.
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);

    return $response->setPrivate();
})->where('any', '^(?!api|sanctum|storage|up).*$');
