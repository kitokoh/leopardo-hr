<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use App\Exceptions\DomainException;

class MissingCheckInException extends DomainException
{
    public function __construct(string $employeeId)
    {
        parent::__construct("No active check-in found for employee [{$employeeId}].", 422);
    }

    public function errorCode(): string
    {
        return 'MISSING_CHECK_IN';
    }
}
