<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;

/**
 * IAM-L6 (#9096) — protection du DERNIER administrateur d'un tenant.
 *
 * ## Le trou que ce service ferme
 *
 * La garde existait, mais sur UN SEUL chemin : `RoleAssignmentController::assign`
 * (#9093). Or `manager_role` et `status` s'écrivent aussi par
 * `EmployeeService::update()` (PATCH /employees/{id}, y compris depuis l'app
 * mobile) et `EmployeeService::archive()`. Un principal pouvait donc :
 *
 *  - se rétrograder via `PATCH /employees/{id}` (`manager_role` → `rh`) ;
 *  - se suspendre via le même PATCH (`status` → `suspended`) ;
 *  - s'archiver via `DELETE /employees/{id}` ;
 *
 * et laisser l'entreprise sans personne capable de nommer un successeur —
 * auto-verrouillage définitif, sans récupération possible côté tenant.
 *
 * ## La règle, en une phrase
 *
 * Une opération qui retire le dernier `principal` ACTIF d'un tenant est
 * refusée. Un principal `suspended`/`archived`/`departed` ne compte pas : il
 * ne peut plus rouvrir la porte.
 *
 * ## Ce que ce service ne fait pas
 *
 * Il ne modifie aucun gate : il REFUSE une écriture, il ne restreint pas une
 * lecture. Il ne s'applique qu'aux transitions qui FONT PERDRE le dernier
 * principal — promouvoir, ou modifier un non-principal, passe toujours.
 */
final class LastAdministratorGuard
{
    /**
     * Le nombre de `principal` encore ACTIFS dans le tenant.
     *
     * Miroir de `PlatformTeamController::activeSuperAdminCount()` : la même
     * mesure protège les deux niveaux (plateforme et tenant).
     */
    public function activePrincipalCount(string $companyId): int
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->where('manager_role', 'principal')
            ->where('status', 'active')
            ->count();
    }

    /**
     * L'opération retire-t-elle le dernier principal actif ?
     *
     * @param  string|null  $newManagerRole  `manager_role` demandé (null = inchangé)
     * @param  string|null  $newStatus  `status` demandé (null = inchangé)
     */
    public function wouldRemoveLastPrincipal(
        Employee $employee,
        ?string $newManagerRole = null,
        ?string $newStatus = null,
    ): bool {
        // Seul un principal ACTIF peut être « le dernier » : un compte déjà
        // suspendu/archivé ne protège plus rien, le refuser bloquerait des
        // opérations légitimes sur un compte inerte.
        if ($employee->manager_role !== 'principal' || $employee->status !== 'active') {
            return false;
        }

        $losesPrincipalRole = $newManagerRole !== null && $newManagerRole !== 'principal';
        $losesActiveStatus = $newStatus !== null && $newStatus !== 'active';

        if (! $losesPrincipalRole && ! $losesActiveStatus) {
            return false;
        }

        if ($employee->company_id === null) {
            return false;
        }

        return $this->activePrincipalCount((string) $employee->company_id) <= 1;
    }
}
