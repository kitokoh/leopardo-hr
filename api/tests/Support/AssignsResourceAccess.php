<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;

/**
 * Issue #7599 (R2 de l'épique #7597) — aide de test : donner à un employé un
 * accès ressource-scopé (« Moussa → restaurant Almadies, niveau manage »).
 *
 * `company_id`/`created_by` sont posés explicitement (non mass-assignables,
 * même garde que le contrôleur R1). Dès la première assignation d'un type
 * dans l'entreprise, le scoping devient actif et fail-closed pour les
 * non-assignés de ce type.
 */
trait AssignsResourceAccess
{
    protected function assignResourceAccess(
        Employee $employee,
        string $resourceType,
        int $resourceId,
        string $level,
        ?Employee $grantedBy = null,
    ): EmployeeResourceAssignment {
        $assignment = new EmployeeResourceAssignment([
            'employee_id' => $employee->id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'access_level' => $level,
        ]);
        $assignment->company_id = $employee->company_id;
        $assignment->created_by = $grantedBy?->id;
        $assignment->save();

        return $assignment;
    }
}
