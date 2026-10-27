<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Contracts\HR\EmployeeReadContract;
use Illuminate\Support\Collection;

/**
 * Implementation HR de {@see EmployeeReadContract} (#8061, tranche 1).
 *
 * Seul endroit du code ou un consommateur hors-HR peut obtenir un perimetre
 * d'employes sans importer le modele. Les requetes sont copiees a l'identique
 * de celles que les consommateurs executaient : le decouplage ne doit rien
 * changer au comportement, seulement au sens des dependances.
 */
final class EmployeeReadAdapter implements EmployeeReadContract
{
    /**
     * @return Collection<int, int>
     */
    public function idsVisibleTo(string $companyId, int $actorId): Collection
    {
        $actor = Employee::query()->find($actorId);

        // Acteur introuvable : on renvoie un perimetre vide plutot que tout
        // l'effectif. Un acteur supprime entre l'authentification et l'appel ne
        // doit pas elargir silencieusement les droits.
        if ($actor === null) {
            return collect();
        }

        return Employee::query()
            ->where('company_id', $companyId)
            ->visibleToManager($actor)
            ->pluck('id');
    }

    /**
     * @return array{id: int, company_id: string, first_name: string|null, last_name: string|null, matricule: string|null}|null
     */
    public function find(int $employeeId): ?array
    {
        $employee = Employee::query()
            ->select(['id', 'company_id', 'first_name', 'last_name', 'matricule'])
            ->find($employeeId);

        if ($employee === null) {
            return null;
        }

        return [
            'id' => (int) $employee->id,
            'company_id' => (string) $employee->company_id,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'matricule' => $employee->matricule,
        ];
    }
}
