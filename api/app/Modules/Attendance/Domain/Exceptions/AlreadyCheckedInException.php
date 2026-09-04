<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use App\Exceptions\DomainException;

class AlreadyCheckedInException extends DomainException
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
                ? "Employee [{$employeeId}] is already checked in."
                : "Vous avez deja pointe votre arrivee aujourd'hui.",
            422
        );
    }

    public function errorCode(): string
    {
        return 'ALREADY_CHECKED_IN';
    }
}
