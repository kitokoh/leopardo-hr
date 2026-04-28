<?php

namespace App\Exceptions;

class PayrollPeriodConflictException extends DomainException
{
    public function __construct(int $month, int $year)
    {
        parent::__construct(sprintf('Une fiche de paie existe déjà pour la période %02d/%04d.', $month, $year));
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'PAYROLL_PERIOD_CONFLICT';
    }
}
