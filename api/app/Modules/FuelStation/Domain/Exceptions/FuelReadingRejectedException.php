<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * FUEL-004 — relevé rejeté (opérateur hors tenant, shift incohérent…).
 */
class FuelReadingRejectedException extends DomainException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason, 422, 'FUEL_READING_REJECTED');
    }
}
