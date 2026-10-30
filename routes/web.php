<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::get('/health', HealthController::class);
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->middleware('throttle:20,1')
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:20,1')
    ->name('auth.google.callback');
