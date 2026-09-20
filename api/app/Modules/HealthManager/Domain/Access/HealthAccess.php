<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Access;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Domain\Models\HealthStaffRole;

/**
 * Socle RBAC HealthManager — HC-001 (#7785).
 *
 * Rôles cliniques (V0, portail employee) :
 * - Direction d'établissement : manager avec manager_role principal|rh, ou
 *   manager sans sous-rôle (propriétaire/fondateur). Gestion complète.
 * - Praticien : employé lié à une fiche `health_practitioners` active.
 *   Périmètre : son agenda, ses consultations, lecture des patients.
 * - Réception : employé porteur du rôle `reception` dans
 *   `health_staff_roles`. Patients (administratif), rendez-vous, admissions.
 * - Facturation : rôle `billing` (ou manager_role `comptable`). Catalogue
 *   d'actes, factures, encaissements.
 *
 * Confidentialité médicale : consultations et prescriptions ne sont
 * visibles que des praticiens et de la direction — JAMAIS de la réception
 * ni d'un employé hors périmètre (deny-by-default, pattern EduAccess).
 */
final class HealthAccess
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

    /**
     * Praticien actif de l'établissement (fiche health_practitioners).
     */
    public static function isPractitioner(Employee $actor): bool
    {
        return HealthPractitioner::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->where('status', HealthPractitioner::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Identifiant de la fiche praticien de l'acteur (null si aucun).
     */
    public static function practitionerId(Employee $actor): ?int
    {
        /** @var int|string|null $id */
        $id = HealthPractitioner::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->where('status', HealthPractitioner::STATUS_ACTIVE)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Personnel de réception (rôle `reception` dans health_staff_roles).
     */
    public static function isReception(Employee $actor): bool
    {
        return self::hasStaffRole($actor, HealthStaffRole::ROLE_RECEPTION);
    }

    /**
     * Personnel de facturation (rôle `billing`, ou manager comptable).
     */
    public static function isBilling(Employee $actor): bool
    {
        if ($actor->isManager() && $actor->hasManagerRole('comptable')) {
            return true;
        }

        return self::hasStaffRole($actor, HealthStaffRole::ROLE_BILLING);
    }

    private static function hasStaffRole(Employee $actor, string $role): bool
    {
        return HealthStaffRole::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->where('role', $role)
            ->exists();
    }
}
