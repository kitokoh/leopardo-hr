<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Levée quand un paiement porterait le total payé au-delà du montant TTC du
 * document (règle « jamais payé > total », DoD #5229). Aucun paiement n'est
 * créé dans ce cas.
 */
class PaymentExceedsTotalException extends DomainException
{
    public function __construct(float $total, float $alreadyPaid, float $attempted)
    {
        parent::__construct(
            sprintf(
                'PAYMENT_EXCEEDS_TOTAL: amount %.2f + already paid %.2f exceeds total %.2f',
                $attempted,
                $alreadyPaid,
                $total
            ),
            422,
            'PAYMENT_EXCEEDS_TOTAL'
        );
    }
}
