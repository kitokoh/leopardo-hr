<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Levée quand un ordre de virement ne peut pas être exécuté (statut inattendu
 * ou déjà exécuté) — issue #5239 (Phase C).
 */
class PaymentOrderNotExecutableException extends DomainException
{
    public function __construct(string $status)
    {
        parent::__construct(
            sprintf("L'ordre de virement ne peut pas être exécuté (statut actuel : %s).", $status),
            422,
            'PAYMENT_ORDER_NOT_EXECUTABLE'
        );
    }
}
