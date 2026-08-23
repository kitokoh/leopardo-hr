<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Levée quand on tente d'enregistrer un paiement sur un document non émis
 * (brouillon ou annulé). Un document brouillon n'a pas d'effet comptable
 * (journal #5234) — les paiements non plus.
 */
class PaymentOnUnsentDocumentException extends DomainException
{
    public function __construct(string $status)
    {
        parent::__construct(
            sprintf('Impossible d\'enregistrer un paiement sur un document au statut « %s ».', $status),
            422,
            'PAYMENT_ON_UNSENT_DOCUMENT'
        );
    }
}
