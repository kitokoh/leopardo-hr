<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Levée quand un posting produit un journal déséquilibré (Σ débit ≠ Σ crédit).
 * L'invariant débit = crédit est une obligation légale (livre journal
 * exploitable par un expert-comptable) — on refuse d'écrire plutôt que de
 * corrompre le journal. Issue #5234.
 */
class UnbalancedJournalEntryException extends DomainException
{
    public function __construct(float $debit, float $credit, string $source)
    {
        parent::__construct(
            sprintf(
                'Écritures déséquilibrées pour %s : débit %.2f ≠ crédit %.2f — posting annulé.',
                $source,
                $debit,
                $credit
            )
        );
    }
}
