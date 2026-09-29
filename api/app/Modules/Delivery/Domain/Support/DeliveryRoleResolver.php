<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Support;

use App\Core\Auth\Domain\Models\Employee;

/**
 * Résolution des rôles delivery d'un employé (BC-26-D05, issue #6294).
 *
 * SOURCE UNIQUE de la correspondance rôles delivery ↔ profil employé,
 * conforme à la matrice documentée `docs/architecture/DELIVERY_RBAC.md`
 * (#8185 — fin de la divergence historique avec la garde câblée) :
 *
 * - `admin`      : manager `principal` (propriétaire/exploitant du tenant) ;
 * - `dispatcher` : manager `principal` | `manager` (chef ops — planification
 *   des tournées) ;
 * - `manager`    : manager `principal` | `manager` | `rh` (supervision,
 *   lecture) ;
 * - `reports`    : alias de `manager` (KPIs — parité manifest) ;
 * - `rider`      : tout employé non-manager — autorisation par PROPRIÉTÉ
 *   (driver_id = id de l'employé), jamais par rôle seul.
 *
 * Tout autre `manager_role` (marketing, comptable, dept, superviseur…) ne
 * donne AUCUN rôle delivery : deny-by-default, conformément à
 * `DeliveryRbacTest::test_marketing_manager_is_denied_everywhere`.
 *
 * Les ensembles de `manager_role` sont exposés en constantes publiques :
 * la garde de routes `EnsureDeliveryRoleMiddleware` les consomme — une
 * seule définition, pas de divergence possible.
 */
final class DeliveryRoleResolver
{
    /** @var list<string> manager_role donnant le rôle `admin`. */
    public const ADMIN_MANAGER_ROLES = ['principal'];

    /** @var list<string> manager_role donnant le rôle `dispatcher`. */
    public const DISPATCHER_MANAGER_ROLES = ['principal', 'manager'];

    /** @var list<string> manager_role donnant les rôles `manager` et `reports`. */
    public const MANAGER_READ_ROLES = ['principal', 'manager', 'rh'];

    /**
     * @return list<string>
     */
    public function rolesFor(Employee $employee): array
    {
        if (! $employee->isManager()) {
            return ['rider'];
        }

        $roles = [];

        if ($employee->hasManagerRole(...self::MANAGER_READ_ROLES)) {
            $roles[] = 'manager';
            $roles[] = 'reports';
        }

        if ($employee->hasManagerRole(...self::ADMIN_MANAGER_ROLES)) {
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
