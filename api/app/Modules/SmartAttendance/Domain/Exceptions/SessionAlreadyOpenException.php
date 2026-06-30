<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Domain\Exceptions;

use RuntimeException;

class SessionAlreadyOpenException extends RuntimeException
{
    public function __construct(int $employeeId, int $sessionId)
    {
        parent::__construct(
            "Employee #{$employeeId} already has an open geo attendance session #{$sessionId}."
        );
    }
}
