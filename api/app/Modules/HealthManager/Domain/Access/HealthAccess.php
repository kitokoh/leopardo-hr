<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Access;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;

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
     * Praticien de l'établissement (health.practitioner) : employé
     * référencé comme praticien ACTIF dans `health_practitioners` (HC-002).
     */
    public static function isPractitioner(Employee $actor): bool
    {
        return HealthPractitioner::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->where('status', HealthPractitioner::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Id du praticien ACTIF lié à l'acteur, ou null s'il n'est pas
     * praticien — sert à borner l'agenda (HC-004) et le dossier médical
     * (HC-005) au praticien lui-même.
     */
    public static function practitionerId(Employee $actor): ?int
    {
        /** @var int|null $id */
        $id = HealthPractitioner::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->where('status', HealthPractitioner::STATUS_ACTIVE)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * L'acteur peut-il GÉRER la structure clinique (services, salles, lits,
     * spécialités, praticiens — HC-002) ? Direction uniquement.
     */
    public static function canManageStructure(Employee $actor): bool
    {
        return self::isAdmin($actor);
    }

    /**
     * L'acteur peut-il GÉRER le registre patients (HC-003) ?
     * Direction (health.admin) et accueil (health.reception).
     */
    public static function canManagePatients(Employee $actor): bool
    {
        return self::isAdmin($actor) || self::isReception($actor);
    }

    /**
     * L'acteur peut-il LIRE le registre patients (HC-003) ? Gestionnaires,
     * praticiens actifs et facturation (couverture assurance) — jamais un
     * employé lambda (deny-by-default).
     */
    public static function canViewPatients(Employee $actor): bool
    {
        return self::canManagePatients($actor) || self::isPractitioner($actor) || self::isBilling($actor);
    }

    /**
     * L'acteur peut-il LIRE la structure clinique ? Direction, accueil et
     * praticiens (jamais un employé lambda — deny-by-default).
     */
    public static function canViewStructure(Employee $actor): bool
    {
        return self::isAdmin($actor) || self::isReception($actor) || self::isPractitioner($actor);
    }

    /**
     * L'acteur peut-il GÉRER les rendez-vous (HC-004) et les admissions
     * (HC-006) ? Direction et accueil (planification / admissions).
     */
    public static function canManageAppointments(Employee $actor): bool
    {
        return self::isAdmin($actor) || self::isReception($actor);
    }

    /**
     * L'acteur peut-il LIRE des rendez-vous / admissions ? Gestionnaires
     * et praticiens actifs — le praticien est ensuite borné à SON agenda
     * par la policy (HC-004 : « le praticien ne voit que son agenda »).
     */
    public static function canViewAppointments(Employee $actor): bool
    {
        return self::canManageAppointments($actor) || self::isPractitioner($actor);
    }

    /**
     * L'acteur peut-il accéder au CONTENU MÉDICAL (consultations,
     * prescriptions — HC-005) ? Praticiens actifs et direction UNIQUEMENT :
     * la réception gère l'administratif mais n'accède JAMAIS au dossier
     * médical (critère d'acceptation HC-005), la facturation non plus.
     */
    public static function canViewMedicalRecords(Employee $actor): bool
    {
        return self::isAdmin($actor) || self::isPractitioner($actor);
    }
}
