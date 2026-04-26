<?php

namespace App\Exceptions;

class EmployeeNotActiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Votre compte employé n\'est pas actif.', 403, 'EMPLOYEE_NOT_ACTIVE');
    }
}
