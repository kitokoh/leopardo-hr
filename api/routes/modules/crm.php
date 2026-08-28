<?php

/**
 * Routes CRM client tenant (issues #5725, #5727, #5728, #5729).
 *
 * Toutes les routes CRM sont tenant-scoped et réservées aux managers
 * `principal`/`rh` (RBAC_ROUTE_MATRIX.md). Les webhooks fournisseur sont
 * publics (hors auth) mais protégés par signature HMAC fail-closed.
 */

use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmAutomationController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmChannelController;
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
        Route::get('/channels/{channel}/observability', [CrmChannelController::class, 'observability']);

        // ── Automatisations CRM (#5728) ────────────────────────────────────
        Route::get('/automations', [CrmAutomationController::class, 'index']);
        Route::post('/automations', [CrmAutomationController::class, 'store']);
        Route::get('/automations/{automation}', [CrmAutomationController::class, 'show']);
        Route::put('/automations/{automation}', [CrmAutomationController::class, 'update']);
        Route::delete('/automations/{automation}', [CrmAutomationController::class, 'destroy']);
        Route::post('/automations/{automation}/activate', [CrmAutomationController::class, 'activate']);
        Route::post('/automations/{automation}/pause', [CrmAutomationController::class, 'pause']);
        Route::post('/automations/{automation}/simulate', [CrmAutomationController::class, 'simulate']);
        Route::get('/automations/{automation}/runs', [CrmAutomationController::class, 'runs']);
        Route::post('/automations/emergency-stop', [CrmAutomationController::class, 'emergencyStop']);
        Route::post('/automations/events/{event}', [CrmAutomationController::class, 'dispatch']);
    });
