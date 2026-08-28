<?php

declare(strict_types=1);

/**
 * Routes du module CRM client (tenant) — issues #5714 (import CSV), #5717
 * (conversion leads), #5718 (déduplication), #5726 (canal email).
 *
 * Le CRM client est strictement séparé du CRM commercial Leopardo
 * (ADR-CRM-002) : toutes les routes vivent sous /api/v1/crm/* dans le
 * groupe authentifié tenant, protégées par Policies + contexte tenant.
 * RBAC : lecture = tout manager du tenant (`api.manager`), écritures =
 * `principal`/`marketing`. Webhook et désabonnement : endpoints publics
 * protégés par secret partagé / jeton signé (aucune session requise).
 */

use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmDedupController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmEmailController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmEmailWebhookController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmImportController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmLeadController;
use Illuminate\Support\Facades\Route;

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

    // ── Canal email (#5726) ──────────────────────────────────────────────────
    Route::middleware('api.manager:principal,marketing')->group(function (): void {
        Route::post('/email/transactional', [CrmEmailController::class, 'sendTransactional']);
        Route::post('/email/marketing', [CrmEmailController::class, 'sendMarketing']);
    });
});

// Endpoints publics du canal email : webhook provider (secret partagé) et
// désabonnement (jeton signé) — volontairement hors auth:sanctum/tenant.
Route::middleware(['throttle:api'])
    ->prefix('crm')
    ->group(function (): void {
        Route::post('/email/webhook', [CrmEmailWebhookController::class, 'handle']);
        Route::post('/email/unsubscribe', [CrmEmailController::class, 'unsubscribe']);
    });
