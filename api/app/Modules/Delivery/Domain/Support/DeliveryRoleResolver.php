<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Support;

use App\Core\Auth\Domain\Models\Employee;

/**
 * Résolution des rôles delivery d'un employé (BC-26-D05, issue #6294).
 *
 * Matrice v1 pragmatique, centrale et documentée (ajustable par tenant en
 * follow-up) :
 *
 * - `admin`      : manager principal (propriétaire/exploitant de l'agence) ;
 * - `dispatcher` : managers principal/operations (planification des tournées) ;
 * - `manager`    : tout manager (supervision, lecture) ;
 * - `reports`    : tout manager (KPIs) ;
 * - `rider`      : tout employé non-manager — autorisation par PROPRIÉTÉ
 *   (driver_id = id de l'employé), jamais par rôle seul.
 */
final class DeliveryRoleResolver
{
    /** @var list<string> */
    private const DISPATCHER_MANAGER_ROLES = ['principal', 'operations'];

    /**
     * @return list<string>
     */
    public function rolesFor(Employee $employee): array
    {
        if (! $employee->isManager()) {
            return ['rider'];
        }

        $roles = ['manager', 'reports'];

        if ($employee->hasManagerRole('principal')) {
            $roles[] = 'admin';
        }

        if ($employee->hasManagerRole(...self::DISPATCHER_MANAGER_ROLES)) {
            $roles[] = 'dispatcher';
        }

        return $roles;
    }

    /**
     * True si l'employé possède au moins un des rôles demandés.
     *
     * @param  list<string>  $required
     */
    public function hasAnyRole(Employee $employee, array $required): bool
    {
        return array_intersect($this->rolesFor($employee), $required) !== [];
    }
}
