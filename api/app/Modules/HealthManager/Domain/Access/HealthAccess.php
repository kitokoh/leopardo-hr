<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Access;

use App\Core\Auth\Domain\Models\Employee;

/**
 * Socle RBAC HealthManager — HC-001 (#7785), pattern EduAccess (EDU-009).
 *
 * Mapping V0 des permissions du manifest sur les rôles tenant existants
 * (`employees.manager_role`, allowlist principal|rh|dept|comptable|
 * superviseur|marketing) — documenté dans la spec §5 :
 *
 * - `health.admin` : manager avec manager_role principal|rh, ou manager sans
 *   sous-rôle (propriétaire). Gestion complète de la structure et du registre.
 * - `health.reception` : accueil / admissions — rôle d'équipe `superviseur`
 *   ou `dept`. Gère le dossier ADMINISTRATIF des patients (HC-003).
 * - `health.billing` : facturation — rôle `comptable`.
 * - `health.practitioner` : employé référencé comme praticien ACTIF dans
 *   `health_practitioners` (HC-002). Lecture seule du registre patients.
 *
 * Deny-by-default : un employé lambda (aucun des rôles ci-dessus) n'accède
 * à AUCUNE ressource HealthManager (403 via les policies).
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
     * Accueil / admissions (health.reception) — rôle d'équipe.
     */
    public static function isReception(Employee $actor): bool
    {
        return $actor->hasManagerRole('superviseur', 'dept');
    }

    /**
     * Facturation (health.billing).
     */
    public static function isBilling(Employee $actor): bool
    {
        return $actor->hasManagerRole('comptable');
    }

    /**
     * L'acteur peut-il GÉRER la structure clinique (services, salles, lits,
     * spécialités, praticiens — HC-002) ? Direction uniquement.
     */
    public static function canManageStructure(Employee $actor): bool
    {
        return self::isAdmin($actor);
    }
}
