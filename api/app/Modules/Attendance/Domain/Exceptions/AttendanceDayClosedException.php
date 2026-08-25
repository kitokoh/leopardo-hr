<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Journée de pointage fermée (issue #5265).
 *
 * Levée quand un pointage (check-in/check-out, import externe, approbation
 * de session géo) tente d'écrire sur un jour verrouillé via
 * `attendance_day_closures` → 409 ATTENDANCE_DAY_CLOSED.
 */
class AttendanceDayClosedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('La journée de pointage est clôturée.', 409, 'ATTENDANCE_DAY_CLOSED');
    }
}
