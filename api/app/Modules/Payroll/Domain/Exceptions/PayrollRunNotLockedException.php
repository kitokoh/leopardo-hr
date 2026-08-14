<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * DZ-DEPTH (#1818) — levée quand on tente de régulariser un run qui n'est
 * PAS verrouillé (seul un run `locked` est régularisable). Rendu 422.
 */
class PayrollRunNotLockedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Seul un run verrouillé (locked) peut être régularisé.',
            422,
            'PAYROLL_RUN_NOT_LOCKED'
        );
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'PAYROLL_RUN_NOT_LOCKED';
    }
}
