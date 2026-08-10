<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * S-3 (#1663) — Le calcul du solde employé (getEmployeeBalance) a échoué.
 *
 * Remplaçait l'ancien comportement de `safeEmployeeBalance()` qui avalait
 * silencieusement l'exception et renvoyait des valeurs vides au client
 * mobile (`warning: partial_balance_fallback`). Désormais l'erreur est
 * visible : HTTP 500 explicite + trace de log, le client ne peut plus
 * croire à un solde de 0 alors que le calcul a échoué.
 */
class PayrollBalanceUnavailableException extends DomainException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            'Impossible de calculer le solde employé : '.$reason,
            500,
            'PAYROLL_BALANCE_UNAVAILABLE'
        );
    }
}
