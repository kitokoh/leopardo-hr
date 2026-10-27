<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Shared\Establishments\EstablishmentAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hub \u00ab Mes \u00e9tablissements \u00bb (#7910).
 *
 * `GET /api/v1/establishments` : liste unifi\u00e9e, en LECTURE SEULE, des
 * \u00e9tablissements de TOUTES les verticales activ\u00e9es du tenant courant
 * (restaurant \u2192 succursales, fuel \u2192 stations, extensible par registre).
 *
 * Surface authentifi\u00e9e tenant (`auth:sanctum` + contexte soci\u00e9t\u00e9) : le hub
 * n'expose que les \u00e9tablissements de la soci\u00e9t\u00e9 de l'acteur. Le contr\u00f4leur ne
 * conna\u00eet aucune verticale \u2014 tout passe par l'agr\u00e9gateur, qui ne conna\u00eet que
 * le contrat partag\u00e9.
 */
class EstablishmentsController extends Controller
{
    public function __construct(
        private readonly EstablishmentAggregationService $establishments,
        private readonly TenantManager $tenants,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        return response()->json($this->establishments->gather($company));
    }

    /**
     * Soci\u00e9t\u00e9 de l'acteur, ou 404.
     *
     * Le contexte tenant est normalement pos\u00e9 par le middleware ; on tol\u00e8re
     * son absence (routes hors groupe tenant) en le r\u00e9solvant depuis
     * l'utilisateur authentifi\u00e9, et on refuse explicitement (404, jamais un
     * hub vide silencieux) si aucune soci\u00e9t\u00e9 n'est d\u00e9terminable.
     */
    private function resolveCompany(Request $request): Company
    {
        $current = $this->tenants->current();

        if ($current instanceof Company) {
            return $current;
        }

        $user = $request->user();

        $companyId = $user instanceof \App\Core\Auth\Domain\Models\Employee
            ? $user->company_id
            : null;

        if (! is_string($companyId) || $companyId === '') {
            abort(404);
        }

        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            abort(404);
        }

        return $company;
    }
}
