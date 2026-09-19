<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * PHARMA-001 (#7798) — la solution PharmaManager n'est pas active sur le
 * tenant (fail-closed : routes inaccessibles tant que le flag `pharmacy`
 * est désactivé).
 */
class PharmacySolutionInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'La solution PharmaManager n\'est pas active pour ce tenant.',
            403,
            'PHARMACY_SOLUTION_INACTIVE'
        );
    }
}
