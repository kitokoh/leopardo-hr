<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HC-007 (#7791) — une facture emise n'est plus modifiable (annulation
 * seulement) : toute tentative de modification hors brouillon → 422.
 */
class HealthInvoiceNotEditableException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Une facture emise n\'est plus modifiable (annulation seulement).',
            422,
            'HEALTH_INVOICE_NOT_EDITABLE'
        );
    }
}
