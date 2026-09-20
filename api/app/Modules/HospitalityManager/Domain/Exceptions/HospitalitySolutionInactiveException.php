<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HOSP-001 (#7943) — la solution HospitalityManager n'est pas active sur le
 * tenant (fail-closed : routes inaccessibles tant que le flag est désactivé).
 */
class HospitalitySolutionInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'La solution HospitalityManager n\'est pas active pour ce tenant.',
            403,
            'HOSPITALITY_SOLUTION_INACTIVE'
        );
    }
}
