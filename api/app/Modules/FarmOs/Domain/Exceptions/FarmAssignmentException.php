<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Exceptions;

use RuntimeException;

/**
 * Farm OS — Affectation refusée (T1.3).
 *
 * Toute affectation invalide est refusée CÔTÉ SERVEUR : un identifiant
 * d'employé ou d'équipe hors de l'entreprise courante ne doit jamais être
 * persisté (isolation multi-tenant, exigence de la mission).
 *
 * Le message est un CODE stable (jamais un libellé français en dur) : le front
 * le traduit via le catalogue i18n (PA2-I18N-007 / #5432).
 */
final class FarmAssignmentException extends RuntimeException
{
    public static function employeeNotInCompany(string $field, int $employeeId): self
    {
        return new self(sprintf('FARM_ASSIGNMENT_EMPLOYEE_NOT_IN_COMPANY:%s:%d', $field, $employeeId));
    }

    public static function teamNotInCompany(int $teamId): self
    {
        return new self(sprintf('FARM_ASSIGNMENT_TEAM_NOT_IN_COMPANY:%d', $teamId));
    }

    /**
     * Code stable exploitable par le contrôleur pour renvoyer un 422 explicite.
     */
    public function errorCode(): string
    {
        return explode(':', $this->getMessage())[0];
    }
}
