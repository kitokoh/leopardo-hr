<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HC-004 (#7788) — chevauchement d'agenda praticien (spec §4) : deux
 * rendez-vous actifs (statut hors cancelled/no_show) du même praticien
 * ne peuvent pas se recouvrir → 409 HEALTH_APPOINTMENT_CONFLICT.
 */
class HealthAppointmentConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Le praticien a déjà un rendez-vous sur ce créneau.',
            409,
            'HEALTH_APPOINTMENT_CONFLICT'
        );
    }
}
