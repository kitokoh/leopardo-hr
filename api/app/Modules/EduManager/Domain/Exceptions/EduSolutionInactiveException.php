<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * EDU-010 — la solution EduManager n'est pas active sur le tenant
 * (fail-closed : routes inaccessibles tant que le flag est désactivé).
 */
class EduSolutionInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'La solution EduManager n\'est pas active pour ce tenant.',
            403,
            'EDU_SOLUTION_INACTIVE'
        );
    }
}
