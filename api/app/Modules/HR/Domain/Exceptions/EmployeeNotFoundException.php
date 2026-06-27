<?php

namespace App\Modules\HR\Domain\Exceptions;

use App\Exceptions\DomainException;

class EmployeeNotFoundException extends DomainException
{
    public function __construct(string $id)
    {
        parent::__construct("Employee [{$id}] not found.", 404);
    }

    public function errorCode(): string
    {
        return 'EMPLOYEE_NOT_FOUND';
    }
}
