<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Levée quand un lettrage est déséquilibré : Σ débits ≠ Σ crédits sur les
 * écritures sélectionnées (tolérance 0.005). Un lettrage rapproche des flux
 * opposés d'un même compte — un écart signale une saisie incomplète ou
 * erronée, on refuse d'écrire. Issue #5422.
 */
class UnbalancedLetteringException extends DomainException
{
    public function __construct(float $debit, float $credit)
    {
        parent::__construct(
            sprintf('Lettrage déséquilibré : débit %.2f ≠ crédit %.2f — lettrage refusé.', $debit, $credit),
            422,
            'LETTERING_UNBALANCED',
        );
    }
}
