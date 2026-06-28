<?php

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

class PayrollAlreadyValidatedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Cette fiche de paie est déjà validée et ne peut plus être modifiée.', 422);
    }

    public function errorCode(): string
    {
        return 'PAYROLL_ALREADY_VALIDATED';
    }
}
