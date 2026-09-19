<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HR\Infrastructure\Services\EmployeeModuleGrantService;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateEmployeeModuleGrantsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #7761 (délégation d'accès, spec MISSION_ESPACE_CLIENT §3.1) — les
 * MODULES délégués à un collaborateur.
 *
 *   GET /v1/employees/{employee}/module-grants  → ce qu'il a aujourd'hui
 *   PUT /v1/employees/{employee}/module-grants  → le nouveau jeu complet
 *
 * Le `PUT` est **remplacement**, pas fusion : c'est la seule forme qui rende
 * la révocation possible en un geste (« Moussa n'a plus la comptabilité »).
 * Les créations et suppressions passent par le modèle (`Auditable`) : chaque
 * geste laisse une ligne dans `audit_logs`, sans code d'audit ici.
 *
 * Autorisation : `principal` du tenant uniquement, et jamais sur un employé
 * d'une autre société (policy `EmployeePolicy::manageModuleGrants`, même
 * doctrine que resource-assignments #7598).
 */
class EmployeeModuleGrantController extends Controller
{
    public function __construct(private readonly EmployeeModuleGrantService $grants) {}

    public function index(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('viewModuleGrants', $employee);

        return new JsonResponse(['data' => ['module_keys' => $this->grants->list($employee)]]);
    }

    public function update(UpdateEmployeeModuleGrantsRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('manageModuleGrants', $employee);

        /** @var Employee $actor */
        $actor = $request->user();
        $companyId = $actor->company_id;

        // La surface API tenant garantit une société courante ; sans elle, un
        // grant n'aurait pas de propriétaire (fail-closed).
        if ($companyId === null) {
            return new JsonResponse([
                'error' => 'INSUFFICIENT_ROLE',
                'message' => __('errors.INSUFFICIENT_ROLE'),
            ], 403);
        }

        $keys = $this->grants->sync($employee, $request->moduleKeys(), $actor, $companyId);

        return new JsonResponse(['data' => ['module_keys' => $keys]]);
    }
}
