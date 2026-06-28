<?php

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

class SalaryAdvanceNotPendingException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Cette avance sur salaire ne peut pas être traitée dans son état actuel.', 422);
    }

    public function errorCode(): string
    {
        return 'ADVANCE_NOT_PENDING';
    }
}
