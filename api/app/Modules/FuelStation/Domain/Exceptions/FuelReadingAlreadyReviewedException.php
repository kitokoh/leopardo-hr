<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * FUEL-004 — relevé déjà corrigé ou intervalle déjà revu.
 */
class FuelReadingAlreadyReviewedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Ce relevé a déjà été corrigé ou revu.',
            409,
            'FUEL_READING_REVIEWED'
        );
    }
}
