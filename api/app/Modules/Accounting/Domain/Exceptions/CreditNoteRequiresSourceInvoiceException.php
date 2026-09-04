<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Une note de crédit doit référencer un document source (règle #5223).
 */
final class CreditNoteRequiresSourceInvoiceException extends DomainException
{
    public function __construct()
    {
        parent::__construct('CREDIT_NOTE_REQUIRES_SOURCE_INVOICE: une note de crédit doit référencer une facture source.', 422, 'CREDIT_NOTE_REQUIRES_SOURCE_INVOICE');
    }
}
