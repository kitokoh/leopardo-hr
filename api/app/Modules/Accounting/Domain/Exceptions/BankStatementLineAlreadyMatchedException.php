<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Levée quand on tente de matcher une ligne de relevé déjà rapprochée
 * (409) ou un paiement déjà rapproché (idempotence stricte).
 */
class BankStatementLineAlreadyMatchedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'BANK_STATEMENT_LINE_ALREADY_MATCHED: line or payment is already matched',
            409,
            'BANK_STATEMENT_LINE_ALREADY_MATCHED'
        );
    }
}
