<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use App\Exceptions\DomainException;

class MissingCheckInException extends DomainException
{
    /**
     * Issue #6573 — le parametre employeeId est optionnel pour rester
     * compatible avec les sites d'appel legacy (message enrichi quand il est
     * fourni).
     */
    public function __construct(string $employeeId = '')
    {
        parent::__construct(
            $employeeId !== ''
                ? "No active check-in found for employee [{$employeeId}]."
                : "Aucun pointage d'arrivee trouve pour aujourd'hui.",
            422
        );
    }

    public function errorCode(): string
    {
        return 'MISSING_CHECK_IN';
    }
}
