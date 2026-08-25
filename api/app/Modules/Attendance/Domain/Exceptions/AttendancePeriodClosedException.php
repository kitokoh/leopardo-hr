<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Issue #5267 — levée quand une demande ou décision de correction de pointage
 * porte sur une date incluse dans une période clôturée
 * (`attendance_period_closures`). La clôture verrouille le mois : aucune
 * correction sans réouverture tracée (422, ATTENDANCE_PERIOD_CLOSED).
 */
class AttendancePeriodClosedException extends DomainException
{
    public function __construct(?string $periodLabel = null)
    {
        parent::__construct(
            $periodLabel !== null
                ? sprintf('La période de pointage %s est clôturée : aucune correction possible.', $periodLabel)
                : 'La période de pointage est clôturée : aucune correction possible.',
            422,
            'ATTENDANCE_PERIOD_CLOSED'
        );
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'ATTENDANCE_PERIOD_CLOSED';
    }
}
