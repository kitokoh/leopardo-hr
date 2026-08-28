<?php

/**
 * Routes CRM client tenant (issues #5725, #5727, #5728, #5729).
 *
 * Toutes les routes CRM sont tenant-scoped et réservées aux managers
 * `principal`/`rh` (RBAC_ROUTE_MATRIX.md). Les webhooks fournisseur sont
 * publics (hors auth) mais protégés par signature HMAC fail-closed.
 */

use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmChannelController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmExportController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmWhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// ── Webhooks fournisseur (publics, signature HMAC, fail-closed) ─────────────
Route::middleware(['throttle:webhooks-inbound'])->group(function (): void {
    Route::get('/crm/webhooks/whatsapp', [CrmWhatsAppWebhookController::class, 'verify']);
    Route::post('/crm/webhooks/whatsapp', [CrmWhatsAppWebhookController::class, 'handle']);
});

// ── Canaux de communication CRM (tenant, managers principal/rh) ─────────────
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager:principal,rh'])
    ->prefix('crm')
    ->group(function (): void {
        Route::get('/channels', [CrmChannelController::class, 'index']);
        Route::post('/channels', [CrmChannelController::class, 'store']);
        Route::patch('/channels/{channel}', [CrmChannelController::class, 'update']);
        Route::post('/channels/{channel}/send', [CrmChannelController::class, 'send']);
        Route::get('/channels/{channel}/messages', [CrmChannelController::class, 'messages']);
        Route::get('/channels/{channel}/conversations', [CrmChannelController::class, 'conversations']);

        // ── Exports CRM asynchrones + read models (#5729) ──────────────────
        Route::get('/exports', [CrmExportController::class, 'index']);
        Route::post('/exports', [CrmExportController::class, 'store']);
        Route::get('/exports/{export}', [CrmExportController::class, 'show']);
        Route::get('/exports/{export}/download', [CrmExportController::class, 'download']);
        Route::get('/read-models', [CrmExportController::class, 'readModels']);
    });
