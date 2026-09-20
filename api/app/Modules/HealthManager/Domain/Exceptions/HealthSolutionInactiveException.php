<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HC-001 (#7785) — la solution HealthManager n'est pas active sur le tenant
 * (fail-closed : routes inaccessibles tant que le flag est désactivé).
 */
class HealthSolutionInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'La solution HealthManager n\'est pas active pour ce tenant.',
            403,
            'HEALTH_SOLUTION_INACTIVE'
        );
    }
}
