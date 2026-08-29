<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Queries\SearchCrmQuery;
use App\Modules\CRM\Interfaces\Api\V1\Requests\CrmSearchRequest;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmSearchResultResource;
use Illuminate\Http\JsonResponse;

/**
 * Issue #5719 — Recherche CRM tenant-scoped (accounts + contacts).
 *
 * RBAC : roles manager principal/rh/marketing (middleware api.manager) +
 * Policy `crm.search` appliquée dans le controller avant toute requête
 * (exigence « company_id et Policy appliqués avant résultat »).
 * Isolation tenant : scope global BelongsToCompany sur les modèles CRM.
 */
class CrmSearchController extends Controller
{
    public function __construct(private readonly SearchCrmQuery $searchCrmQuery) {}

    public function index(CrmSearchRequest $request): JsonResponse
    {
        $this->authorize('crm.search');

        $results = $this->searchCrmQuery->execute($request->validated());

        return CrmSearchResultResource::collection($results)->response();
    }
}
