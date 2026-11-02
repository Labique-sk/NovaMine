<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LinkedInAuthController;
use Illuminate\Http\Request;
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
Route::get('/auth/linkedin/redirect', [LinkedInAuthController::class, 'redirect'])
    ->middleware('throttle:20,1')
    ->name('auth.linkedin.redirect');
Route::get('/auth/linkedin/callback', [LinkedInAuthController::class, 'callback'])
    ->middleware('throttle:20,1')
    ->name('auth.linkedin.callback');
Route::get('/password/reset/{token}', function (Request $request, string $token) {
    return view('app', ['passwordResetToken' => $token, 'passwordResetEmail' => (string) $request->query('email', '')]);
})->name('password.reset');
