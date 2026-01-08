<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\ForgotController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\Exchange\ProfileController;
use App\Http\Controllers\Api\Exchange\OrdersController;


## ================== SETTINGS ================== ##
Route::get('/settings', [SettingsController::class, 'index']);
## ================== SETTINGS ================== ##





## ------------------ AUTH ROUTES ------------------ ##
Route::controller(AuthController::class)->group(function () {
    Route::post('/register',     'register')->middleware('guest');
    Route::post('/verify-otp',   'verifyOtp')->middleware('guest');
    Route::post('/resend-otp',   'resendOtp')->middleware('guest');
    Route::post('/login',        'login')->middleware('guest');
    Route::post('/logout',       'logout')->middleware('auth:sanctum');
});
## ------------------ AUTH ROUTES ------------------ ##




## ------------------ Forgot Password ------------------ ##
Route::post('/forgot/password',         [ForgotController::class, 'forgotPassword'])->middleware('guest');
Route::post('/forgot/verify-otp',       [ForgotController::class, 'verifyOtp'])->middleware('guest');
Route::post('/forgot/resend-otp',       [ForgotController::class, 'resendOtp'])->middleware('guest');
Route::post('/forgot/reset-password',   [ForgotController::class, 'resetPassword'])->middleware('guest');
## ------------------ Forgot Password ------------------ ##


## ================== EXCHANGE (Assessment) ================== ##
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);

    Route::get('/orders', [OrdersController::class, 'index']);
    Route::get('/orders/orderbook', [OrdersController::class, 'orderbook']);
    Route::post('/orders', [OrdersController::class, 'store']);
    Route::post('/orders/{id}/cancel', [OrdersController::class, 'cancel']);

    // Optional testing endpoint
    Route::post('/orders/{id}/match', [OrdersController::class, 'match']);
});
## ================== EXCHANGE (Assessment) ================== ##


