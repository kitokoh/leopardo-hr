<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Exceptions;

use RuntimeException;

/**
 * Transition d'etat interdite dans la verticale Trade — BC-36 TRADE.
 *
 * Levee par les machines a etats (RFQ, offre, commande) ; les controleurs la
 * traduisent en 422 `INVALID_STATE_TRANSITION` (registre `ApiError`), de sorte
 * que l'appelant recoit un refus EXPLICITE — jamais un etat silencieusement
 * incoherent.
 */
final class InvalidTradeStateTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $subject,
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct(sprintf(
            'Transition Trade invalide pour %s : %s -> %s.',
            $subject,
            $from,
            $to,
        ));
    }
}
