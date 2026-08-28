<?php

/**
 * Routes CRM client tenant (issues #5714, #5717, #5718, #5725, #5727, #5728, #5729).
 *
 * Le CRM client est strictement séparé du CRM commercial Leopardo
 * (ADR-CRM-002) : toutes les routes vivent sous /api/v1/crm/* dans le
 * groupe authentifié tenant, protégées par Policies + contexte tenant.
 * Les webhooks fournisseur sont publics (hors auth) mais protégés par
 * signature HMAC fail-closed.
 */

use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmChannelController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmDedupController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmExportController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmImportController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmLeadController;
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

// ── Import CSV / leads / déduplication (issues #5714, #5717, #5718) ─────────
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->prefix('crm')->group(function (): void {
    // ── Import CSV (issue #5714) ─────────────────────────────────────────────
    Route::post('/imports', [CrmImportController::class, 'store']);
    Route::get('/imports/{crmImport}', [CrmImportController::class, 'show']);
    Route::post('/imports/{crmImport}/commit', [CrmImportController::class, 'commit']);
    Route::post('/imports/{crmImport}/cancel', [CrmImportController::class, 'cancel']);
    // ── Leads (issue #5717) ──────────────────────────────────────────────────
    Route::post('/leads/{crmLead}/convert', [CrmLeadController::class, 'convert']);
    // ── Déduplication & fusion supervisée (issue #5718) ──────────────────────
    Route::get('/dedup/suggestions', [CrmDedupController::class, 'suggestions']);
    Route::get('/merge/preview', [CrmDedupController::class, 'preview']);
    Route::post('/merge', [CrmDedupController::class, 'merge']);
});
