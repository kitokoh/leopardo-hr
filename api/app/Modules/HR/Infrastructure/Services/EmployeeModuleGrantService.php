<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;
use Illuminate\Support\Facades\DB;

/**
 * Issue #7761 (délégation d'accès, spec MISSION_ESPACE_CLIENT §3.1) — pose et
 * révocation des grants de MODULES d'un collaborateur.
 *
 * Le `sync()` est **remplacement**, pas fusion : ce qui n'est pas envoyé est
 * révoqué (seule forme qui rende la révocation possible en un geste, même
 * doctrine que le PUT resource-assignments #7598). Les créations et
 * suppressions passent par le modèle `EmployeeModuleGrant` (`Auditable`) :
 * chaque geste laisse une ligne dans `audit_logs`.
 */
class EmployeeModuleGrantService
{
    /**
     * Clés de modules explicitement accordées au collaborateur.
     *
     * @return list<string>
     */
    public function list(Employee $employee): array
    {
        return $employee->grantedModuleKeys();
    }

    /**
     * Remplace le jeu complet des grants du collaborateur.
     *
     * @param  list<string>  $moduleKeys  Clés du registre fermé `ModuleKey`
     *                                    (déjà validées par le FormRequest).
     * @return list<string> Le jeu effectif après remplacement.
     */
    public function sync(Employee $employee, array $moduleKeys, Employee $actor, string $companyId): array
    {
        $desired = array_values(array_unique($moduleKeys));

        DB::transaction(function () use ($employee, $actor, $companyId, $desired): void {
            foreach ($employee->moduleGrants()->get() as $existing) {
                if (! in_array($existing->module_key, $desired, true)) {
                    $existing->delete();
                }
            }

            foreach ($desired as $moduleKey) {
                $exists = $employee->moduleGrants()
                    ->where('module_key', $moduleKey)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $grant = new EmployeeModuleGrant([
                    'employee_id' => $employee->id,
                    'module_key' => $moduleKey,
                ]);
                $grant->company_id = $companyId;
                $grant->granted_by_employee_id = $actor->id;
                $grant->save();
            }
        });

        return $this->list($employee);
    }
}
