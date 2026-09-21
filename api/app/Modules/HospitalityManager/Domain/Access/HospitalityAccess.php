<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Access;

use App\Core\Auth\Domain\Models\Employee;

/**
 * Socle RBAC HospitalityManager — HOSP-002 (#7944, BC-32).
 *
 * Deny-by-default (spec §4) : gestion réservée à la direction — manager
 * avec manager_role principal|rh, ou manager sans sous-rôle
 * (propriétaire/fondateur).
 *
 * HOSP-003 (#7945) ajoutera le RBAC ressource-scopé par établissement
 * (`hospitality_property` + EmployeeResourceAssignment, pattern restaurant
 * #7598/#7599) avec le même fallback progressif : tant qu'aucune
 * assignation n'existe dans le tenant, la direction historique ci-dessous
 * reste la référence.
 */
final class HospitalityAccess
{
    /**
     * Direction / administration de l'établissement (gestion complète).
     */
    public static function isAdmin(Employee $actor): bool
    {
        if (! $actor->isManager()) {
            return false;
        }

        $role = $actor->manager_role;

        return $role === null || in_array($role, ['principal', 'rh'], true);
    }
}
