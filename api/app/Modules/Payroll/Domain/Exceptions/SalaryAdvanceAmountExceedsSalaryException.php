<?php

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

class SalaryAdvanceAmountExceedsSalaryException extends DomainException
{
    public function __construct(float $requested, float $maxAllowed)
    {
        parent::__construct(
            sprintf(
                'Le montant demandé (%.2f) dépasse le maximum autorisé (%.2f).',
                $requested,
                $maxAllowed
            ),
            422
        );
    }

    public function errorCode(): string
    {
        return 'ADVANCE_EXCEEDS_SALARY';
    }
}
