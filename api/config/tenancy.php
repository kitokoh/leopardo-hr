<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Fail-closed des requêtes tenant sans contexte (issue #3727)
    |--------------------------------------------------------------------------
    |
    | Lorsqu'une requête sur un modèle `BelongsToCompany` s'exécute en HTTP
    | sans `current_company` liée ET sans contrainte `company_id` explicite,
    | le scope global était fail-open : la requête balayait toutes les
    | compagnies (schéma partagé) — fuite cross-tenant silencieuse.
    |
    | `fail_closed_without_context` : true → exception
    | `MissingTenantContextException` (recommandé, défaut) ; false → comportement
    | historique (fail-open).
    |
    | En console/jobs/commands, le comportement historique est conservé
    | (les jobs DOIVENT passer par TenantManager::withinTenant ou l'opt-out
    | `withoutGlobalScopes('company')`).
    |
    */

    'fail_closed_without_context' => env('TENANT_FAIL_CLOSED_WITHOUT_CONTEXT', true),

];
