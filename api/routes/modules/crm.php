<?php

declare(strict_types=1);

/**
 * Routes du module CRM client (tenant) — issues #5714 (import CSV), #5717
 * (conversion leads), #5718 (déduplication), #5722 (consentements), #5723
 * (segments), #5724 (campagnes), #5725/#5727/#5728/#5729 (canaux, imports).
 *
 * Le CRM client est strictement séparé du CRM commercial Leopardo
 * (ADR-CRM-002) : toutes les routes vivent sous /api/v1/crm/* dans le
 * groupe authentifié tenant, protégées par Policies + contexte tenant.
 * RBAC : lecture = tout manager du tenant (`api.manager`), écritures =
 * `principal`/`marketing`. Isolation tenant BelongsToCompany (fail-closed
 * #3727).
 */

use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmChannelController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmConsentController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmDedupController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmImportController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmLeadController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmSegmentController;
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
    });

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

    // ── Consentements et préférences de communication (#5722) ───────────────
    Route::middleware('api.manager')->group(function (): void {
        Route::get('/consents', [CrmConsentController::class, 'index']);
        Route::get('/consents/{consent}', [CrmConsentController::class, 'show'])->whereNumber('consent');
    });

    Route::middleware('api.manager:principal,marketing')->group(function (): void {
        Route::post('/consents', [CrmConsentController::class, 'store']);
        Route::post('/consents/{consent}/revoke', [CrmConsentController::class, 'revoke'])->whereNumber('consent');
    });

    // ── Segments CRM (#5723) ─────────────────────────────────────────────────
    Route::middleware('api.manager')->group(function (): void {
        Route::get('/segments', [CrmSegmentController::class, 'index']);
        Route::get('/segments/{segment}', [CrmSegmentController::class, 'show'])->whereNumber('segment');
        Route::get('/segments/{segment}/members', [CrmSegmentController::class, 'members'])->whereNumber('segment');
    });

    Route::middleware('api.manager:principal,marketing')->group(function (): void {
        Route::post('/segments', [CrmSegmentController::class, 'store']);
        Route::put('/segments/{segment}', [CrmSegmentController::class, 'update'])->whereNumber('segment');
        Route::delete('/segments/{segment}', [CrmSegmentController::class, 'destroy'])->whereNumber('segment');
        Route::post('/segments/{segment}/rebuild', [CrmSegmentController::class, 'rebuild'])->whereNumber('segment');
    });
});
