<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Un bon de livraison doit avoir une date de livraison (règle #5223).
 */
final class DeliveryNoteRequiresDeliveryDateException extends DomainException
{
    public function __construct()
    {
        parent::__construct('DELIVERY_NOTE_REQUIRES_DELIVERY_DATE: un bon de livraison doit avoir une date de livraison.', 422, 'DELIVERY_NOTE_REQUIRES_DELIVERY_DATE');
    }
}
