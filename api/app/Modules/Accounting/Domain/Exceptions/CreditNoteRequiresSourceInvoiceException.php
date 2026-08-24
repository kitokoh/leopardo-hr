<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use RuntimeException;

/**
 * Un avoir (credit_note) doit être lié à sa facture source avant d'être émis
 * (règle de transition #5223).
 */
final class CreditNoteRequiresSourceInvoiceException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('CREDIT_NOTE_REQUIRES_SOURCE_INVOICE: un avoir doit être lié à sa facture source.');
    }
}
