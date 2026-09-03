<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\GoogleController;
use App\Http\Controllers\Api\MiningController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ShortlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth');
    Route::get('me', [AuthController::class, 'me']);
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class);
    Route::get('analytics', AnalyticsController::class);
    Route::get('settings', [SettingsController::class, 'show']);
    Route::put('settings', [SettingsController::class, 'update']);

    Route::post('mining', [MiningController::class, 'store'])->middleware('throttle:20,1');
    Route::get('mining/{mining}', [MiningController::class, 'show']);
    Route::post('mining/{mining}/cancel', [MiningController::class, 'cancel']);

    Route::get('candidates', [CandidateController::class, 'index']);
    Route::post('candidates', [CandidateController::class, 'store']);
    Route::get('candidates/{candidate}', [CandidateController::class, 'show']);
    Route::put('candidates/{candidate}', [CandidateController::class, 'update']);
    Route::delete('candidates/{candidate}', [CandidateController::class, 'destroy']);
    Route::post('candidates/{candidate}/shortlist', [CandidateController::class, 'addToShortlist']);
    Route::delete('candidates/{candidate}/shortlist', [CandidateController::class, 'removeFromShortlist']);

    Route::get('shortlists', [ShortlistController::class, 'index']);
    Route::post('shortlists', [ShortlistController::class, 'store']);
    Route::put('shortlists/{shortlist}', [ShortlistController::class, 'update']);
    Route::delete('shortlists/{shortlist}', [ShortlistController::class, 'destroy']);

    Route::get('searches', [SearchController::class, 'index']);

    Route::get('exports', [ExportController::class, 'index']);
    Route::post('exports', [ExportController::class, 'store'])->middleware('throttle:20,1');
    Route::get('exports/{export}/download', [ExportController::class, 'download']);

    Route::get('google/status', [GoogleController::class, 'status']);
    Route::post('google/connect', [GoogleController::class, 'connect']);
    Route::get('google/callback', [GoogleController::class, 'callback']);
    Route::post('google/export', [GoogleController::class, 'export']);
});
