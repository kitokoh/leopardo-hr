<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Exceptions;

use App\Modules\Paiements\Domain\Enums\PaymentTransactionStatus;
use RuntimeException;

/**
 * Transition de statut interdite par la machine a etats (BC-38, #8680).
 */
class InvalidTransactionTransitionException extends RuntimeException
{
    public function __construct(
        public readonly PaymentTransactionStatus $from,
        public readonly PaymentTransactionStatus $to,
    ) {
        parent::__construct(sprintf(
            'Transition de transaction interdite : %s -> %s.',
            $from->value,
            $to->value,
        ));
    }
}
