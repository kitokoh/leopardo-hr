<?php

namespace App\Exceptions;

class EmployeeNotActiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct('EMPLOYEE_NOT_ACTIVE', 403, 'EMPLOYEE_NOT_ACTIVE');
    }
}
