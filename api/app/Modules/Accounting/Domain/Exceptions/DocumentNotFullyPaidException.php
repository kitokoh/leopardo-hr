<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Un document ne peut pas passer à `paid` sans paiement couvrant le total TTC
 * (règle de transition #5223 : pas de « payé » sans paiement).
 */
final class DocumentNotFullyPaidException extends DomainException
{
    public function __construct(float $totalTtc, float $paidAmount)
    {
        parent::__construct(
            sprintf('DOCUMENT_NOT_FULLY_PAID: total %.2f, paid %.2f', $totalTtc, $paidAmount),
            422,
            'DOCUMENT_NOT_FULLY_PAID'
        );
    }
}
