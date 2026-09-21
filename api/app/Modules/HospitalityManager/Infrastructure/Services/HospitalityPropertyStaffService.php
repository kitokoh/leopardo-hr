<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityPropertyStaff;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Règles métier des affectations staff ↔ établissement — HOSP-003 (#7945).
 *
 * - Tenant-scope : un employé d'un AUTRE tenant ne peut pas être affecté
 *   (contrôle `company_id` strict → 422 EMPLOYEE_OUTSIDE_TENANT, pattern
 *   `RestaurantBranchStaffService::assign()` #7909 / `FuelShiftService`).
 * - Unicité : un employé n'est affecté qu'une fois par établissement — un
 *   doublon actif (non supprimé) répond 409 EMPLOYEE_ALREADY_ASSIGNED.
 * - Retrait : soft delete ; une ré-affectation restaure la ligne supprimée
 *   (l'unicité `(company_id, property_id, employee_id)` porte aussi sur
 *   les lignes soft-deleted — jamais de doublon physique).
 * - #8019 : l'affectation est TRANSACTIONNELLE (verrou de la ligne existante
 *   + rattrapage de la violation d'unicité `23505`) — le check-then-create
 *   hors transaction rendait la course « deux affectations simultanées » en
 *   500 au lieu de 409 `EMPLOYEE_ALREADY_ASSIGNED`.
 */
final class HospitalityPropertyStaffService
{
    /**
     * Affecte un employé à un établissement, après contrôle tenant + unicité.
     *
     * @param  array<string, mixed>  $data
     */
    public function assign(HospitalityProperty $property, Employee $actor, array $data): HospitalityPropertyStaff
    {
        $rawEmployeeId = $data['employee_id'] ?? null;
        $employeeId = is_numeric($rawEmployeeId) ? (int) $rawEmployeeId : 0;

        // Un employé d'un autre tenant ne peut pas être affecté ici.
        $employee = Employee::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($employeeId)
            ->first();

        abort_if($employee === null, 422, 'EMPLOYEE_OUTSIDE_TENANT');

        try {
            return DB::transaction(function () use ($property, $employeeId, $data): HospitalityPropertyStaff {
                /** @var HospitalityPropertyStaff|null $existing */
                $existing = HospitalityPropertyStaff::query()
                    ->withTrashed()
                    ->where('company_id', $property->company_id)
                    ->where('property_id', $property->getKey())
                    ->where('employee_id', $employeeId)
                    ->lockForUpdate()
                    ->first();

                // Déjà affecté (ligne non supprimée) → conflit métier.
                abort_if($existing !== null && ! $existing->trashed(), 409, 'EMPLOYEE_ALREADY_ASSIGNED');

                if ($existing !== null) {
                    // Ré-affectation après retrait : restauration de la ligne
                    // soft-deleted (l'unique en base couvre aussi les supprimées).
                    $existing->restore();
                    $existing->update([
                        'role' => $data['role'] ?? null,
                        'assigned_at' => now(),
                    ]);

                    return $existing->refresh();
                }

                return HospitalityPropertyStaff::query()->create([
                    'company_id' => $property->company_id,
                    'property_id' => $property->getKey(),
                    'employee_id' => $employeeId,
                    'role' => $data['role'] ?? null,
                    'assigned_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Course perdue : une affectation concurrente (ou un doublon arrivé
            // entre le SELECT et l'INSERT — le verrou ne bloquant pas une ligne
            // inexistante) a posé la ligne en premier. L'index unique
            // `(company_id, property_id, employee_id)` est la garde ultime :
            // on rend le MÊME 409 que le chemin nominal, jamais un 500.
            abort(409, 'EMPLOYEE_ALREADY_ASSIGNED');
        }
    }
}
