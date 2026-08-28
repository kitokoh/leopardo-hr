<?php

/**
 * Routes CRM client — V0 (issues #5709-5712).
 *
 * Espace client (tenant) : pipelines, stages, leads, opportunités,
 * timeline (append-only) et tâches, plus import CSV (#5714), conversion
 * lead → account/contact/opportunity (#5717) et déduplication/fusion
 * supervisée (#5718) déjà mergés sur main. Le CRM commercial Leopardo
 * reste dans l'admin plateforme (Platform/Marketing) — aucun
 * chevauchement de routes.
 *
 * L'autorisation est portée par les Policies Crm*Policy (jamais de garde
 * inline) : managers `principal`/`rh`/`marketing` en gestion complète,
 * assigné/owner en lecture ciblée (tâches). L'isolation tenant est
 * garantie par le middleware `tenant` + le scope global BelongsToCompany
 * (binding de route 404 cross-tenant).
 */

use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmActivityController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmDedupController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmImportController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmLeadController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmOpportunityController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmPipelineController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmPipelineStageController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('crm')
    ->group(function (): void {
        // -----------------------------------------------------------------
        // Import CSV (issue #5714, mergé sur main)
        // -----------------------------------------------------------------
        Route::post('/imports', [CrmImportController::class, 'store']);
        Route::get('/imports/{crmImport}', [CrmImportController::class, 'show']);
        Route::post('/imports/{crmImport}/commit', [CrmImportController::class, 'commit']);
        Route::post('/imports/{crmImport}/cancel', [CrmImportController::class, 'cancel']);

        // -----------------------------------------------------------------
        // Pipelines (configuration) + stages
        // -----------------------------------------------------------------
        Route::get('/pipelines', [CrmPipelineController::class, 'index']);
        Route::post('/pipelines', [CrmPipelineController::class, 'store']);
        Route::get('/pipelines/{crmPipeline}', [CrmPipelineController::class, 'show']);
        Route::patch('/pipelines/{crmPipeline}', [CrmPipelineController::class, 'update']);
        Route::delete('/pipelines/{crmPipeline}', [CrmPipelineController::class, 'destroy']);

        Route::get('/pipelines/{crmPipeline}/stages', [CrmPipelineStageController::class, 'index']);
        Route::post('/pipelines/{crmPipeline}/stages', [CrmPipelineStageController::class, 'store']);
        Route::patch('/pipelines/{crmPipeline}/stages/{crmPipelineStage}', [CrmPipelineStageController::class, 'update']);
        Route::delete('/pipelines/{crmPipeline}/stages/{crmPipelineStage}', [CrmPipelineStageController::class, 'destroy']);

        // -----------------------------------------------------------------
        // Leads (CRUD V0 #5711 + conversion #5717)
        // -----------------------------------------------------------------
        Route::get('/leads', [CrmLeadController::class, 'index']);
        Route::post('/leads', [CrmLeadController::class, 'store']);
        Route::get('/leads/{crmLead}', [CrmLeadController::class, 'show']);
        Route::patch('/leads/{crmLead}', [CrmLeadController::class, 'update']);
        Route::delete('/leads/{crmLead}', [CrmLeadController::class, 'destroy']);
        Route::post('/leads/{crmLead}/convert', [CrmLeadController::class, 'convert']);

        // -----------------------------------------------------------------
        // Opportunités
        // -----------------------------------------------------------------
        Route::get('/opportunities', [CrmOpportunityController::class, 'index']);
        Route::post('/opportunities', [CrmOpportunityController::class, 'store']);
        Route::get('/opportunities/{crmOpportunity}', [CrmOpportunityController::class, 'show']);
        Route::patch('/opportunities/{crmOpportunity}', [CrmOpportunityController::class, 'update']);
        Route::delete('/opportunities/{crmOpportunity}', [CrmOpportunityController::class, 'destroy']);

        // -----------------------------------------------------------------
        // Timeline (append-only : index + store uniquement)
        // -----------------------------------------------------------------
        Route::get('/activities', [CrmActivityController::class, 'index']);
        Route::post('/activities', [CrmActivityController::class, 'store']);

        // -----------------------------------------------------------------
        // Tâches
        // -----------------------------------------------------------------
        Route::get('/tasks', [CrmTaskController::class, 'index']);
        Route::post('/tasks', [CrmTaskController::class, 'store']);
        Route::get('/tasks/{crmTask}', [CrmTaskController::class, 'show']);
        Route::patch('/tasks/{crmTask}', [CrmTaskController::class, 'update']);
        Route::delete('/tasks/{crmTask}', [CrmTaskController::class, 'destroy']);

        // -----------------------------------------------------------------
        // Déduplication & fusion supervisée (issue #5718, mergé sur main)
        // -----------------------------------------------------------------
        Route::get('/dedup/suggestions', [CrmDedupController::class, 'suggestions']);
        Route::get('/merge/preview', [CrmDedupController::class, 'preview']);
        Route::post('/merge', [CrmDedupController::class, 'merge']);
    });
