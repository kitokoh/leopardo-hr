<?php

use App\Http\Controllers\AI\AIGatewayController;
use App\Http\Middleware\AI\AIFeatureCheck;
use App\Http\Middleware\AI\AIRateLimiter;
use App\Http\Middleware\AI\AITenantInjector;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', AIFeatureCheck::class, AITenantInjector::class])->prefix('ai')->group(function () {

    Route::middleware([AIRateLimiter::class])->group(function () {
        Route::post('/chat', [AIGatewayController::class, 'chat']);
    });

    Route::get('/chat/history', [AIGatewayController::class, 'history']);
    Route::delete('/chat/{conversationId}', [AIGatewayController::class, 'deleteConversation'])->whereNumber('conversationId');
    Route::get('/tools', [AIGatewayController::class, 'tools']);
});
