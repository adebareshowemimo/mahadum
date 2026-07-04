<?php

use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// One-click marketing unsubscribe (signed link embedded in campaign emails).
Route::get('/email/unsubscribe/{email}', UnsubscribeController::class)
    ->middleware('signed')
    ->name('email.unsubscribe');
