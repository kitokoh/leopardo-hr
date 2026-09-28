<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityPropertyStaff;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityPropertyStaffService;

/**
 * Affectation d'un employé à un établissement (BOS-024d, #8215) —
 * extraite de HospitalityPropertyStaffController::store. Comportement
 * conservé à l'identique : contrôle tenant strict (422
 * EMPLOYEE_OUTSIDE_TENANT), unicité (409 EMPLOYEE_ALREADY_ASSIGNED),
 * ré-affectation = restauration de la ligne soft-deleted (jamais de
 * doublon physique), course sérialisée (transaction + verrou).
 */
final class AssignHospitalityStaffAction
{
    public function __construct(private readonly HospitalityPropertyStaffService $staff) {}

    /**
     * @param  array<string, mixed>  $validated  Payload validé (StoreHospitalityPropertyStaffRequest)
     */
    public function execute(HospitalityProperty $property, Employee $actor, array $validated): HospitalityPropertyStaff
    {
        $assignment = $this->staff->assign($property, $actor, $validated);

        return $assignment->load('employee');
    }
}
