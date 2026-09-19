<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HC-004 (#7788) — le créneau demandé chevauche un rendez-vous ACTIF
 * (scheduled|confirmed|checked_in) du même praticien → 409.
 */
class HealthAppointmentConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Chevauchement de rendez-vous pour ce praticien sur ce creneau.',
            409,
            'HEALTH_APPOINTMENT_CONFLICT'
        );
    }
}
