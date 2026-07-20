<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use App\Exceptions\DomainException;

class AlreadyCheckedInException extends DomainException
{
    public function __construct(string $employeeId)
    {
        parent::__construct("Employee [{$employeeId}] is already checked in.", 422);
    }

    public function errorCode(): string
    {
        return 'ALREADY_CHECKED_IN';
    }
}
