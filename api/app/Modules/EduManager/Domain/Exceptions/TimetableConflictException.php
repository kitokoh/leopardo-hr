<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Issue #5822 (EDU-006) — conflit d'emploi du temps.
 *
 * Levée par `TimetableService::create` quand un créneau proposé chevauche un
 * créneau existant du MÊME tenant : même classe sur un intervalle [start,end)
 * qui se recoupe, OU même enseignant sur un intervalle qui se recoupe
 * (422, TIMETABLE_CONFLICT).
 */
class TimetableConflictException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 422, 'TIMETABLE_CONFLICT');
    }
}
