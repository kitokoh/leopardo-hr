<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HC-006 (#7790) — le patient a deja un sejour ACTIF (admitted ou
 * transferred) : une seule hospitalisation a la fois → 409.
 */
class HealthPatientAlreadyAdmittedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Ce patient a deja une hospitalisation en cours.',
            409,
            'HEALTH_PATIENT_ALREADY_ADMITTED'
        );
    }
}
