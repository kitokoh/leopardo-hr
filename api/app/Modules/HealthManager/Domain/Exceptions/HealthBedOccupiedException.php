<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HC-006 (#7790) — le lit visé n'est pas libre (spec §4) : admission ou
 * transfert refusé en 409 `HEALTH_BED_OCCUPIED` (invariant du service, en
 * transaction + verrou pessimiste sur la ligne du lit).
 */
class HealthBedOccupiedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Le lit demandé est déjà occupé.',
            409,
            'HEALTH_BED_OCCUPIED'
        );
    }
}
