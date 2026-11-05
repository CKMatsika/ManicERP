<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PortalApiController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::middleware(['council:citizen', 'throttle:api'])->group(function () {
            Route::get('/accounts', [PortalApiController::class, 'accounts']);
            Route::get('/accounts/{account}', [PortalApiController::class, 'account']);
            Route::get('/accounts/{account}/bills', [PortalApiController::class, 'bills']);
            Route::get('/accounts/{account}/payments', [PortalApiController::class, 'payments']);
            Route::get('/payments', [PortalApiController::class, 'allPayments']);
            Route::post('/payments/intents', [PortalApiController::class, 'createIntent']);

            Route::post('/account-links', [PortalApiController::class, 'requestLink']);
            Route::post('/account-links/{link}/verify', [PortalApiController::class, 'verifyLink']);
            Route::delete('/account-links/{link}', [PortalApiController::class, 'revokeLink']);

            Route::get('/service-requests', [PortalApiController::class, 'serviceRequests']);
            Route::post('/service-requests', [PortalApiController::class, 'storeServiceRequest']);
        });
    });

    Route::post('/webhooks/payments/sandbox', [WebhookController::class, 'sandbox']);
});
