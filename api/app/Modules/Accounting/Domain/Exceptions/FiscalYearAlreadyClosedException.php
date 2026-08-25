<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Levée quand on tente de clôturer un exercice comptable inexistant ou déjà
 * clôturé. La clôture d'exercice est irréversible (périodes figées + report
 * du résultat) : on refuse toute re-clôture. Issue #5422.
 */
class FiscalYearAlreadyClosedException extends DomainException
{
    public function __construct(int $year)
    {
        parent::__construct(
            sprintf("L'exercice comptable %d est déjà clôturé ou n'existe pas — clôture refusée.", $year),
            422,
            'FISCAL_YEAR_ALREADY_CLOSED',
        );
    }
}
