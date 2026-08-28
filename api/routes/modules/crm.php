<?php

declare(strict_types=1);

/**
 * Routes Module CRM client — Issue #5711 (CRM-V0-07) / #5712 (CRM-V0-08).
 *
 * CRM CLIENT tenant : espace client / API tenant, distinct des routes
 * Platform CRM (super-admin, `GET /api/v1/platform/crm/pipeline`).
 *
 * RBAC : toutes les routes exigent un manager du tenant
 * (middleware `api.manager:principal,rh` → 403 MANAGER_REQUIRED /
 * INSUFFICIENT_ROLE pour les autres) ; la garde applicative fine est portée
 * par les Policies `App\Policies\Crm\*` (aucune garde inline).
 *
 * Isolation tenant : `BelongsToCompany` (scope global fail-closed #3727) →
 * toute ressource d'un autre tenant est introuvable (404), jamais visible.
 */

use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmAccountController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmActivityController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmContactController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmLeadController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmOpportunityController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmPipelineController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('crm')
    ->middleware('api.manager:principal,rh')
    ->group(function (): void {

        // ── Leads ────────────────────────────────────────────────────────────
        Route::get('/leads', [CrmLeadController::class, 'index']);
        Route::post('/leads', [CrmLeadController::class, 'store']);
        Route::get('/leads/{lead}', [CrmLeadController::class, 'show'])->whereNumber('lead');
        Route::put('/leads/{lead}', [CrmLeadController::class, 'update'])->whereNumber('lead');
        Route::delete('/leads/{lead}', [CrmLeadController::class, 'destroy'])->whereNumber('lead');

        // ── Opportunités ─────────────────────────────────────────────────────
        Route::get('/opportunities', [CrmOpportunityController::class, 'index']);
        Route::post('/opportunities', [CrmOpportunityController::class, 'store']);
        Route::get('/opportunities/{opportunity}', [CrmOpportunityController::class, 'show'])->whereNumber('opportunity');
        Route::put('/opportunities/{opportunity}', [CrmOpportunityController::class, 'update'])->whereNumber('opportunity');
        Route::delete('/opportunities/{opportunity}', [CrmOpportunityController::class, 'destroy'])->whereNumber('opportunity');

        // ── Timeline (append-only) ───────────────────────────────────────────
        Route::get('/activities', [CrmActivityController::class, 'index']);
        Route::post('/activities', [CrmActivityController::class, 'store']);
        Route::get('/activities/{activity}', [CrmActivityController::class, 'show'])->whereNumber('activity');
        Route::delete('/activities/{activity}', [CrmActivityController::class, 'destroy'])->whereNumber('activity');

        // ── Tâches ───────────────────────────────────────────────────────────
        Route::get('/tasks', [CrmTaskController::class, 'index']);
        Route::post('/tasks', [CrmTaskController::class, 'store']);
        Route::get('/tasks/{task}', [CrmTaskController::class, 'show'])->whereNumber('task');
        Route::put('/tasks/{task}', [CrmTaskController::class, 'update'])->whereNumber('task');
        Route::delete('/tasks/{task}', [CrmTaskController::class, 'destroy'])->whereNumber('task');

        // ── Comptes ──────────────────────────────────────────────────────────
        Route::get('/accounts', [CrmAccountController::class, 'index']);
        Route::post('/accounts', [CrmAccountController::class, 'store']);
        Route::get('/accounts/{account}', [CrmAccountController::class, 'show'])->whereNumber('account');
        Route::put('/accounts/{account}', [CrmAccountController::class, 'update'])->whereNumber('account');
        Route::delete('/accounts/{account}', [CrmAccountController::class, 'destroy'])->whereNumber('account');

        // ── Contacts ─────────────────────────────────────────────────────────
        Route::get('/contacts', [CrmContactController::class, 'index']);
        Route::post('/contacts', [CrmContactController::class, 'store']);
        Route::get('/contacts/{contact}', [CrmContactController::class, 'show'])->whereNumber('contact');
        Route::put('/contacts/{contact}', [CrmContactController::class, 'update'])->whereNumber('contact');
        Route::delete('/contacts/{contact}', [CrmContactController::class, 'destroy'])->whereNumber('contact');

        // ── Pipelines & étapes ───────────────────────────────────────────────
        Route::get('/pipelines', [CrmPipelineController::class, 'index']);
        Route::post('/pipelines', [CrmPipelineController::class, 'store']);
        Route::get('/pipelines/{pipeline}', [CrmPipelineController::class, 'show'])->whereNumber('pipeline');
        Route::put('/pipelines/{pipeline}', [CrmPipelineController::class, 'update'])->whereNumber('pipeline');
        Route::delete('/pipelines/{pipeline}', [CrmPipelineController::class, 'destroy'])->whereNumber('pipeline');
        Route::post('/pipelines/{pipeline}/stages', [CrmPipelineController::class, 'storeStage'])->whereNumber('pipeline');
        Route::delete('/pipelines/{pipeline}/stages/{stage}', [CrmPipelineController::class, 'destroyStage'])
            ->whereNumber('pipeline')
            ->whereNumber('stage');
    });
