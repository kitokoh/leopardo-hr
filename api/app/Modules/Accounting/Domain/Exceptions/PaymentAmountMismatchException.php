<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #5272 — Anti-fraude montant ≠ (US2.4) : le montant notifié par la
 * passerelle DÉPASSE le solde restant du document (tolérance 2 unités
 * mineures). Refus + alerte, aucun rapprochement. Un montant ≤ solde est un
 * paiement partiel légitime (US2.5 → partially_paid).
 */
class PaymentAmountMismatchException extends DomainException
{
    public function __construct(float $total, float $alreadyPaid, int $notifiedMinor)
    {
        parent::__construct(
            sprintf(
                'PAYMENT_AMOUNT_MISMATCH: gateway notified %d (minor) which exceeds remaining balance %.2f (total %.2f, paid %.2f)',
                $notifiedMinor,
                round($total - $alreadyPaid, 2),
                $total,
                $alreadyPaid
            ),
            422,
            'PAYMENT_AMOUNT_MISMATCH'
        );
    }
}
