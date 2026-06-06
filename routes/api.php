<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Exchange\ProfileController;
use App\Http\Controllers\Api\Exchange\OrdersController;





## ------------------ AUTH ROUTES ------------------ ##
Route::controller(AuthController::class)->group(function () {
    Route::post('/register',     'register')->middleware('guest');
    Route::post('/verify-otp',   'verifyOtp')->middleware('guest');
    Route::post('/resend-otp',   'resendOtp')->middleware('guest');
    Route::post('/login',        'login')->middleware('guest');
    Route::post('/logout',       'logout')->middleware('auth:sanctum');
});
## ------------------ AUTH ROUTES ------------------ ##

## ================== EXCHANGE (Assessment) ================== ##
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);

    Route::get('/orders', [OrdersController::class, 'index']);
    Route::get('/my-orders', [OrdersController::class, 'myOrders']);
    Route::post('/orders', [OrdersController::class, 'store']);
    Route::post('/orders/{id}/cancel', [OrdersController::class, 'cancel']);
});
## ================== EXCHANGE (Assessment) ================== ##
