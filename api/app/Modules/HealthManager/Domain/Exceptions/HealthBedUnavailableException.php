<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HC-006 (#7790) — le lit vise n'est pas disponible (occupe, en
 * maintenance, ou deja porteur d'un sejour actif) → 409.
 */
class HealthBedUnavailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Ce lit n\'est pas disponible.',
            409,
            'HEALTH_BED_UNAVAILABLE'
        );
    }
}
