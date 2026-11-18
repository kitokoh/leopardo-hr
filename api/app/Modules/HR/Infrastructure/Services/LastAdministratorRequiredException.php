<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Exceptions\DomainException;

/**
 * IAM-L6 (#9096) — l'opération retirerait le DERNIER administrateur actif du
 * tenant.
 *
 * Levée par `EmployeeService::update()` / `archive()` AVANT toute écriture,
 * quand la transition demandée ferait perdre le dernier `principal` actif.
 *
 * Étend `DomainException` : le renderer existant (`bootstrap/app.php`) la
 * traduit en `422 LAST_PRINCIPAL_REQUIRED` avec le message localisé du
 * catalogue `errors.LAST_PRINCIPAL_REQUIRED` — exactement le code et le
 * `error` que `RoleAssignmentController::assign` (#9093) renvoie déjà. Une
 * seule sémantique pour les trois chemins d'écriture.
 */
final class LastAdministratorRequiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'At least one active principal must remain in the company.',
            422,
            'LAST_PRINCIPAL_REQUIRED',
        );
    }

    public function statusCode(): int
    {
        return 422;
    }
}
