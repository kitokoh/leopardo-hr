<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Levée quand un posting tente d'écrire dans une période comptable clôturée.
 * Le journal clôturé est figé (audit trail) : les documents/paiements datés
 * dans une période close ne peuvent plus être passés. Issue #5234.
 */
class PeriodClosedException extends DomainException
{
    public function __construct(string $period)
    {
        parent::__construct(
            sprintf('La période comptable %s est clôturée — aucun posting possible.', $period),
            422,
            'PERIOD_CLOSED'
        );
    }
}
