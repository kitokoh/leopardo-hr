<?php

/**
 * Routes CRM client tenant (issues #5725, #5727, #5728, #5729).
 *
 * Toutes les routes CRM sont tenant-scoped et réservées aux managers
 * `principal`/`rh` (RBAC_ROUTE_MATRIX.md). Les webhooks fournisseur sont
 * publics (hors auth) mais protégés par signature HMAC fail-closed.
 */

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
    });

 * Routes du module CRM client (tenant) — issue #5714 (import CSV).
 * Le CRM client est strictement séparé du CRM commercial Leopardo
 * (ADR-CRM-002) : toutes les routes vivent sous /api/v1/crm/* dans le
 * groupe authentifié tenant, protégées par Policies + contexte tenant.
 * Le reste du périmètre API (CRUD accounts/contacts/leads/pipelines/…)
 * arrive avec CRM-V0-08 (#5712).
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmDedupController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmImportController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmLeadController;
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