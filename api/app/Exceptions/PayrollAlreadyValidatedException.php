<?php

namespace App\Exceptions;

class PayrollAlreadyValidatedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Cette fiche de paie est déjà validée et ne peut plus être modifiée.');
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'PAYROLL_ALREADY_VALIDATED';
    }
}
