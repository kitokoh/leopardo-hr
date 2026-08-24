<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use RuntimeException;

/**
 * Un document ne peut pas passer à `paid` sans paiement couvrant le total TTC
 * (règle de transition #5223 : pas de « payé » sans paiement).
 */
final class DocumentNotFullyPaidException extends RuntimeException
{
    public function __construct(float $totalTtc, float $paidAmount)
    {
        parent::__construct(sprintf(
            'DOCUMENT_NOT_FULLY_PAID: total %s, payé %s',
            $totalTtc,
            $paidAmount,
        ));
    }
}
