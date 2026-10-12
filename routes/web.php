<?php

use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\PublicWebsiteController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Middleware\CanonicalPublicHost;
use Illuminate\Support\Facades\Route;

// One-click marketing unsubscribe (signed link embedded in campaign emails).
Route::get('/email/unsubscribe/{email}', UnsubscribeController::class)
    ->middleware('signed')
    ->name('email.unsubscribe');

Route::middleware(CanonicalPublicHost::class)->group(function () {
    Route::get('/sitemap.xml', [PublicContentController::class, 'sitemap']);
    Route::get('/robots.txt', [PublicContentController::class, 'robots']);
    Route::get('/llms.txt', [PublicContentController::class, 'llms']);
    Route::get('/languages', [PublicContentController::class, 'languages']);
    Route::get('/learn-{language}', [PublicContentController::class, 'language'])->where('language', 'igbo|yoruba|hausa');
    Route::get('/guides', [PublicContentController::class, 'guides']);
    Route::get('/guides/{slug}', [PublicContentController::class, 'guide'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
    Route::get('/vote', [PublicContentController::class, 'vote']);
});

// Serve the built React SPA for every route except the API, Sanctum, storage,
// and health-check paths. The deploy script builds `web/` and copies its
// index.html to resources/spa/index.html; client-side routing (React Router)
// takes over from there. Keep this LAST — routes above must match first.
Route::get('/{any?}', [PublicWebsiteController::class, 'shell'])
    ->middleware(CanonicalPublicHost::class)
    ->where('any', '^(?!api(?:/|$)|sanctum(?:/|$)|storage(?:/|$)|up$).*$');
