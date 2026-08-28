<?php

declare(strict_types=1);

/**
 * Routes CRM client — V1 (issues #5719/#5720/#5721/#5722, batch agent PM).
 *
 * Espace client tenant — distinct du CRM commercial Platform/Marketing
 * (cf. ADR-CRM-DUAL-CONTEXTS). Toutes les routes sont :
 *  - tenant-scoped : middleware `tenant` + modèles `BelongsToCompany`
 *    (`company_id` non nullable) ;
 *  - soumises au RBAC manager (principal/rh/marketing) et aux Policies CRM ;
 *  - strictement contrôlées (entrées, filtres, tris, statuts allowlistés).
 *
 * Fichier volontairement SÉPARÉ de `routes/modules/crm.php` (routes V0,
 * issue #5712) pour éviter les collisions inter-agents : les deux fichiers
 * coexistent sous le préfixe `/api/v1/crm/*`.
 */

use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmSearchController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmTaskController;
use App\Modules\CRM\Interfaces\Api\V1\Controllers\CrmTimelineController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager:principal,rh,marketing'])
    ->prefix('crm')
    ->group(function (): void {
        // Recherche tenant-scoped accounts/contacts (issue #5719).
        Route::get('/search', [CrmSearchController::class, 'index']);

        // ----------------------------------------------------------------
        // Tâches CRM + timeline d'account (issue #5720).
        // Filtres allowlistés (ListCrmTasksRequest), Policy CrmTaskPolicy,
        // cursor pagination sur la timeline (before_id, id DESC).
        // ----------------------------------------------------------------
        Route::get('/tasks', [CrmTaskController::class, 'index']);
        Route::post('/tasks', [CrmTaskController::class, 'store']);
        Route::get('/tasks/{task}', [CrmTaskController::class, 'show'])->whereNumber('task');
        Route::patch('/tasks/{task}', [CrmTaskController::class, 'update'])->whereNumber('task');
        Route::post('/tasks/{task}/complete', [CrmTaskController::class, 'complete'])->whereNumber('task');
        Route::post('/tasks/{task}/reopen', [CrmTaskController::class, 'reopen'])->whereNumber('task');
        Route::delete('/tasks/{task}', [CrmTaskController::class, 'destroy'])->whereNumber('task');

        Route::get('/accounts/{account}/timeline', [CrmTimelineController::class, 'index'])->whereNumber('account');
    });
