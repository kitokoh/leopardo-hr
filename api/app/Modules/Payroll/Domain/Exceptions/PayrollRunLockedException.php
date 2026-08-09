<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Programme FOCUS (F-11) — tentative de modification d'un run de paie verrouillé
 * (clôture comptable). Aucune modification silencieuse après verrouillage.
 *
 * Étend DomainException pour bénéficier du rendu JSON API standard
 * (errorCode + statusCode, cf. bootstrap/app.php) : 423 Locked.
 */
class PayrollRunLockedException extends DomainException
{
    public function __construct(string $message = 'Ce run de paie est verrouillé (clôture comptable) et ne peut plus être modifié.')
    {
        parent::__construct($message, 423);
    }

    public function errorCode(): string
    {
        return 'PAYROLL_RUN_LOCKED';
    }
}
