<?php

declare(strict_types=1);

namespace App\Core\Solutions\Exceptions;

use App\Exceptions\DomainException;

/**
 * FUEL-001 — un module requis par la solution n'est pas actif sur le tenant.
 */
class SolutionMissingDependencyException extends DomainException
{
    /** @param list<string> $missing */
    public function __construct(public readonly array $missing)
    {
        parent::__construct(
            sprintf('Modules requis inactifs : %s.', implode(', ', $missing)),
            422,
            'SOLUTION_MISSING_DEPENDENCY'
        );
    }
}
