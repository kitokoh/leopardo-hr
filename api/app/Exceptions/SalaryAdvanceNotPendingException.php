<?php

namespace App\Exceptions;

class SalaryAdvanceNotPendingException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Cette avance sur salaire ne peut pas être traitée dans son état actuel.');
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'ADVANCE_NOT_PENDING';
    }
}
