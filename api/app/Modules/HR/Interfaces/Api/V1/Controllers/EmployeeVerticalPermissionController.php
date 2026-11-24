<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\Contracts\VerticalPermissionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\HR\Infrastructure\Services\EmployeeModuleGrantService;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateEmployeeVerticalPermissionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #EVAL — permissions VERTICALES déléguées à un collaborateur.
 *
 *   GET /v1/employees/{employee}/vertical-permissions  → ce qu'il a aujourd'hui
 *   PUT /v1/employees/{employee}/vertical-permissions  → le nouveau jeu complet
 *
 * Pourquoi une surface distincte de `module-grants` : `ModuleKey` est un
 * registre FERMÉ de modules HORIZONTAUX, et l'ouvrir aux permissions de
 * manifest casserait l'invariant « manifest inactif ⇒ aucune permission
 * effective ». Ici le registre est DYNAMIQUE et borné au tenant : il ne
 * contient que les permissions des solutions actives (`travel.agent`,
 * `travel.checkin`, `travel.reports`, `travel.manage`, …).
 *
 * Autorisation : `principal` du tenant uniquement, jamais sur un employé d'une
 * autre société (policy `EmployeePolicy::manageModuleGrants`, même doctrine
 * que #7761/#7598).
 */
class EmployeeVerticalPermissionController extends Controller
{
    public function __construct(
        private readonly EmployeeModuleGrantService $grants,
        private readonly VerticalPermissionCatalogue $catalogue,
    ) {}

    public function index(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('viewModuleGrants', $employee);

        $company = $this->company($request);

        if ($company === null) {
            return new JsonResponse([
                'error' => 'INSUFFICIENT_ROLE',
                'message' => __('errors.INSUFFICIENT_ROLE'),
            ], 403);
        }

        $delegable = $this->catalogue->forCompany($company);

        return new JsonResponse([
            'data' => [
                'permission_keys' => $this->grants->listVertical($employee, array_keys($delegable)),
                // Le front compose ses cases à cocher avec ce catalogue : il ne
                // peut donc jamais proposer une permission d'une verticale
                // inactive (fail-closed côté UI ET côté API).
                'available' => $delegable,
            ],
        ]);
    }

    public function update(UpdateEmployeeVerticalPermissionsRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('manageModuleGrants', $employee);

        /** @var Employee $actor */
        $actor = $request->user();

        $company = $this->company($request);

        if ($company === null) {
            return new JsonResponse([
                'error' => 'INSUFFICIENT_ROLE',
                'message' => __('errors.INSUFFICIENT_ROLE'),
            ], 403);
        }

        $delegable = $this->catalogue->codesForCompany($company);

        $keys = $this->grants->syncVertical(
            $employee,
            $request->permissionKeys(),
            $actor,
            (string) $actor->company_id,
            $delegable,
        );

        return new JsonResponse(['data' => ['permission_keys' => $keys]]);
    }

    private function company(Request $request): ?Company
    {
        /** @var Employee|null $actor */
        $actor = $request->user();

        if ($actor === null || $actor->company_id === null) {
            return null;
        }

        return Company::query()->find($actor->company_id);
    }
}
