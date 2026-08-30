<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * FUEL-004 — la solution FuelStation n'est pas active sur le tenant
 * (fail-closed : routes inaccessibles tant que le flag est désactivé).
 */
class FuelSolutionInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'La solution FuelStation n\'est pas active pour ce tenant.',
            403,
            'FUEL_SOLUTION_INACTIVE'
        );
    }
}
