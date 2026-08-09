<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

class PayrollAlreadyValidatedException extends DomainException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'Cette fiche de paie est déjà validée et ne peut plus être modifiée.', 422);
    }

    public function errorCode(): string
    {
        return 'PAYROLL_ALREADY_VALIDATED';
    }
}
