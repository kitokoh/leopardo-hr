<?php

use App\Http\Controllers\AI\AgentController;
use App\Http\Controllers\AI\AIAnalyticsController;
use App\Http\Controllers\AI\AIGatewayController;
use App\Http\Controllers\AI\ConversationExportController;
use App\Http\Controllers\AI\VoiceController;
use App\Http\Middleware\AI\AIFeatureCheck;
use App\Http\Middleware\AI\AIRateLimiter;
use App\Http\Middleware\AI\AITenantInjector;
use App\Http\Middleware\AI\EnsureAIAnalyticsAccess;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\AIWorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'throttle:ai-sensitive', AIFeatureCheck::class, AITenantInjector::class, 'throttle:api-plan'])->prefix('ai')->group(function () {

    // Phase 1 — Chat IA
    Route::middleware([AIRateLimiter::class])->group(function () {
        Route::post('/chat', [AIGatewayController::class, 'chat']);
    });

    Route::get('/chat/history', [AIGatewayController::class, 'history']);
    Route::delete('/chat/{conversationId}', [AIGatewayController::class, 'deleteConversation'])->whereNumber('conversationId');
    Route::post('/actions/{pendingActionId}/confirm', [AIGatewayController::class, 'confirmAction']);
    Route::post('/actions/{pendingActionId}/reject', [AIGatewayController::class, 'rejectAction']);
    Route::get('/tools', [AIGatewayController::class, 'tools']);

    // BC-23-D07 (issue #6239) — export asynchrone de conversation (idempotent,
    // file `ai`, DLQ dédiée + replay via `php artisan ai:dlq:replay`).
    Route::post('/conversations/{conversationId}/export', [ConversationExportController::class, 'export'])->whereNumber('conversationId');
    Route::get('/exports/{exportId}', [ConversationExportController::class, 'show'])->whereNumber('exportId');

    // Phase 3 — Voice IA (Sprint 17-18)
    Route::middleware([AIRateLimiter::class])->group(function () {
        Route::post('/voice/transcribe', [VoiceController::class, 'transcribe']);
        Route::post('/voice/synthesize', [VoiceController::class, 'synthesize']);
        Route::post('/voice/command', [VoiceController::class, 'command']);
    });

    // Phase 4 — Agents autonomes (Sprint 17-18)
    Route::middleware([AIRateLimiter::class])->group(function () {
        Route::post('/agent/run', [AgentController::class, 'run']);
        Route::get('/agent/workflows', [AgentController::class, 'workflows']);
    });

    // Phase 5 — Workflows metier (C9, C10)
    Route::middleware([AIRateLimiter::class])->group(function () {
        Route::post('/workflows/prepare-payroll', [AIWorkflowController::class, 'preparePayroll']);
        Route::get('/workflows/weekly-report', [AIWorkflowController::class, 'weeklyReport']);
    });

    // Phase 2 — Analytics IA (Principal/RH only, Sprint 17-18)
    Route::middleware([EnsureAIAnalyticsAccess::class])->group(function () {
        Route::get('/analytics/usage', [AIAnalyticsController::class, 'usage']);
        Route::get('/analytics/costs', [AIAnalyticsController::class, 'costs']);
        Route::get('/analytics/tools', [AIAnalyticsController::class, 'tools']);
        Route::get('/analytics/errors', [AIAnalyticsController::class, 'errors']);
    });
});
