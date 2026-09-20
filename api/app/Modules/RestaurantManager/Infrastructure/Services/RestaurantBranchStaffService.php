<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranchStaff;

/**
 * Règles métier des affectations staff ↔ succursale restaurant (#7909).
 *
 * - Tenant-scope : un employé d'un AUTRE tenant ne peut pas être affecté
 *   (contrôle `company_id` strict → 422 EMPLOYEE_OUTSIDE_TENANT, même
 *   pattern que `FuelShiftService::assign()`).
 * - Unicité : un employé n'est affecté qu'une fois par succursale — un
 *   doublon actif (non supprimé) répond 409 EMPLOYEE_ALREADY_ASSIGNED.
 * - Retrait : soft delete ; une ré-affectation restaure la ligne supprimée
 *   (l'unicité `(company_id, branch_id, employee_id)` porte aussi sur les
 *   lignes soft-deleted, on ne recrée donc jamais un doublon physique).
 */
final class RestaurantBranchStaffService
{
    /**
     * Affecte un employé à une succursale, après contrôle tenant + unicité.
     *
     * @param  array<string, mixed>  $data
     */
    public function assign(RestaurantBranch $branch, Employee $actor, array $data): RestaurantBranchStaff
    {
        $rawEmployeeId = $data['employee_id'] ?? null;
        $employeeId = is_numeric($rawEmployeeId) ? (int) $rawEmployeeId : 0;

        // Un employé d'un autre tenant ne peut pas être affecté ici.
        $employee = Employee::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($employeeId)
            ->first();

        abort_if($employee === null, 422, 'EMPLOYEE_OUTSIDE_TENANT');

        $existing = RestaurantBranchStaff::query()
            ->withTrashed()
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id)
            ->where('employee_id', $employeeId)
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

        return RestaurantBranchStaff::query()->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'employee_id' => $employeeId,
            'role' => $data['role'] ?? null,
            'assigned_at' => now(),
        ]);
    }
}
