<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Core\Tenant\Infrastructure\Services\ResourceTypeRegistry;
use App\Http\Controllers\Controller;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateEmployeeResourceAssignmentsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Issue #7598 (R1 de l'épique #7597) — les accès RESSOURCE d'un collaborateur.
 *
 *   GET /v1/employees/{employee}/resource-assignments  → ce qu'il a aujourd'hui
 *   PUT /v1/employees/{employee}/resource-assignments  → le nouveau jeu complet
 *
 * Le `PUT` est **remplacement**, pas fusion : c'est la seule forme qui rende la
 * révocation possible en un geste (« Moussa ne gère plus Almadies »). Les
 * suppressions, créations et changements de niveau passent par le modèle, qui
 * est `Auditable` : chaque geste laisse une ligne dans `audit_logs`, sans code
 * d'audit ici.
 *
 * Autorisation : `principal` du tenant uniquement, et jamais sur un employé
 * d'une autre société (policy `EmployeePolicy::manageResourceAssignments`).
 */
class EmployeeResourceAssignmentController extends Controller
{
    public function __construct(private readonly ResourceTypeRegistry $registry) {}

    public function index(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('viewResourceAssignments', $employee);

        return new JsonResponse(['data' => $this->present($employee)]);
    }

    public function update(UpdateEmployeeResourceAssignmentsRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('manageResourceAssignments', $employee);

        /** @var Employee $actor */
        $actor = $request->user();
        $companyId = $actor->company_id;

        // La surface API tenant garantit une société courante ; sans elle, une
        // écriture d'assignation n'aurait pas de propriétaire (fail-closed).
        if ($companyId === null) {
            return new JsonResponse([
                'error' => 'RESOURCE_ACCESS_DENIED',
                'message' => __('errors.RESOURCE_ACCESS_DENIED'),
            ], 403);
        }

        $desired = $request->assignments();

        // Une ressource inexistante (ou d'un autre tenant) n'est jamais
        // assignable : on refuse explicitement plutôt que d'écrire une ligne
        // qui ne correspondra à rien.
        foreach ($desired as $entry) {
            if (! $this->registry->exists($entry['resource_type'], $entry['resource_id'], $companyId)) {
                return new JsonResponse([
                    'error' => 'RESOURCE_NOT_FOUND',
                    'message' => __('errors.RESOURCE_NOT_FOUND'),
                    'resource_type' => $entry['resource_type'],
                    'resource_id' => $entry['resource_id'],
                ], 422);
            }
        }

        DB::transaction(function () use ($employee, $actor, $companyId, $desired): void {
            $keep = array_map(
                static fn (array $entry): string => $entry['resource_type'].':'.$entry['resource_id'],
                $desired
            );

            foreach ($employee->resourceAssignments()->get() as $existing) {
                if (! in_array($existing->resource_type.':'.$existing->resource_id, $keep, true)) {
                    $existing->delete();
                }
            }

            foreach ($desired as $entry) {
                $assignment = $employee->resourceAssignments()
                    ->where('resource_type', $entry['resource_type'])
                    ->where('resource_id', $entry['resource_id'])
                    ->first();

                if ($assignment === null) {
                    $assignment = new EmployeeResourceAssignment([
                        'employee_id' => $employee->id,
                        'resource_type' => $entry['resource_type'],
                        'resource_id' => $entry['resource_id'],
                        'access_level' => $entry['access_level'],
                    ]);
                    $assignment->company_id = $companyId;
                    $assignment->created_by = $actor->id;
                    $assignment->save();

                    continue;
                }

                if ($assignment->access_level !== $entry['access_level']) {
                    $assignment->access_level = $entry['access_level'];
                    $assignment->save();
                }
            }
        });

        return new JsonResponse(['data' => $this->present($employee)]);
    }

    /**
     * Les assignations du collaborateur, enrichies du libellé de la ressource
     * (le mobile et la console ne doivent pas afficher un identifiant nu).
     *
     * @return list<array{resource_type: string, resource_id: int, access_level: string, resource_label: string}>
     */
    private function present(Employee $employee): array
    {
        $assignments = $employee->resourceAssignments()
            ->orderBy('resource_type')
            ->orderBy('resource_id')
            ->get();

        $labels = [];
        foreach ($assignments->groupBy('resource_type') as $type => $rows) {
            /** @var string $type */
            $ids = [];
            foreach ($rows as $row) {
                $ids[] = (int) $row->resource_id;
            }

            foreach ($this->registry->listForCompany($type, $employee->company_id, $ids) as $entry) {
                $labels[$type.':'.$entry['id']] = $entry['label'];
            }
        }

        /** @var list<array{resource_type: string, resource_id: int, access_level: string, resource_label: string}> $presented */
        $presented = [];
        foreach ($assignments as $assignment) {
            $presented[] = [
                'resource_type' => (string) $assignment->resource_type,
                'resource_id' => (int) $assignment->resource_id,
                'access_level' => (string) $assignment->access_level,
                'resource_label' => $labels[$assignment->resource_type.':'.$assignment->resource_id] ?? '',
            ];
        }

        return $presented;
    }
}
