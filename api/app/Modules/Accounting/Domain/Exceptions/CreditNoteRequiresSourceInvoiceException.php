<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Un avoir (credit_note) doit être lié à sa facture source avant d'être émis
 * (règle de transition #5223).
 */
final class CreditNoteRequiresSourceInvoiceException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'CREDIT_NOTE_REQUIRES_SOURCE_INVOICE',
            422,
            'CREDIT_NOTE_REQUIRES_SOURCE_INVOICE'
        );
    }
}
