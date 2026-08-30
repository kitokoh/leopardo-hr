<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * FUEL-004 — relevé daté dans le futur au-delà de la dérive configurée.
 */
class FuelReadingFutureException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Le releve est date dans le futur (derive d\'horloge depassee).',
            422,
            'FUEL_READING_FUTURE'
        );
    }
}
