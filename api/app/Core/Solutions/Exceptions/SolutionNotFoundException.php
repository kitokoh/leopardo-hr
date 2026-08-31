<?php

declare(strict_types=1);

namespace App\Core\Solutions\Exceptions;

use App\Exceptions\DomainException;

/**
 * FUEL-001 — code de solution inconnu de l'allowlist (fail-closed).
 */
class SolutionNotFoundException extends DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(
            sprintf('Solution inconnue : "%s".', $code),
            404,
            'SOLUTION_NOT_FOUND'
        );
    }
}
