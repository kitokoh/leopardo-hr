<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Support;

use App\Core\Auth\Domain\Models\Employee;

/**
 * Résolution des rôles VTC d'un employé (BC-34 VTC, VTC-05/#8361).
 *
 * SOURCE UNIQUE de la correspondance rôles vtc ↔ profil employé (matrice
 * `docs/architecture/VTC_RBAC.md`, VTC-06) — même pattern que
 * DeliveryRoleResolver (BC-26-D05, #8185) :
 *
 * - `admin`      : manager `principal` (propriétaire/exploitant du tenant) ;
 * - `dispatcher` : manager `principal` | `manager` (chef ops — console de
 *   répartition, suivi temps réel) ;
 * - `driver`     : employé actif rattaché à une fiche chauffeur (user_id) —
 *   le périmètre est SES offres et SES courses, jamais par rôle seul.
 *
 * Tout autre profil (marketing, comptable, rh…) ne donne AUCUN rôle vtc :
 * deny-by-default.
 */
final class VtcRoleResolver
{
    /** @var list<string> manager_role donnant le rôle `admin`. */
    public const ADMIN_MANAGER_ROLES = ['principal'];

    /** @var list<string> manager_role donnant le rôle `dispatcher`. */
    public const DISPATCHER_MANAGER_ROLES = ['principal', 'manager'];

    /**
     * @return list<string>
     */
    public function rolesFor(Employee $employee): array
    {
        if (! $employee->isManager()) {
            return $employee->isEmployee() && $employee->status === 'active'
                ? ['driver']
                : [];
        }

        $roles = [];

        if ($employee->hasManagerRole(...self::ADMIN_MANAGER_ROLES)) {
            $roles[] = 'admin';
        }

        if ($employee->hasManagerRole(...self::DISPATCHER_MANAGER_ROLES)) {
            $roles[] = 'dispatcher';
        }

        return $roles;
    }
}
