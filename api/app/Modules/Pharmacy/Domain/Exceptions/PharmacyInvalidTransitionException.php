<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * PHARMA-004 (#7801) — transition d'état refusée sur le cycle de vie d'une
 * commande d'achat (ex. réception d'un `draft`, annulation d'un `received`).
 */
class PharmacyInvalidTransitionException extends DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(
            sprintf('Transition invalide : %s → %s.', $from, $to),
            422,
            'PHARMACY_INVALID_TRANSITION'
        );
    }
}
