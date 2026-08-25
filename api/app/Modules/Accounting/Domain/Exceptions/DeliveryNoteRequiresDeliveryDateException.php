<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Un bordereau de livraison (delivery_note) doit porter sa date de livraison
 * avant d'être émis (règle de transition #5223).
 */
final class DeliveryNoteRequiresDeliveryDateException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'DELIVERY_NOTE_REQUIRES_DELIVERY_DATE',
            422,
            'DELIVERY_NOTE_REQUIRES_DELIVERY_DATE'
        );
    }
}
