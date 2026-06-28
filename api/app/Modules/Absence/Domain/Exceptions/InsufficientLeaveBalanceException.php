<?php

declare(strict_types=1);

namespace App\Modules\Absence\Domain\Exceptions;

use App\Exceptions\DomainException;

class InsufficientLeaveBalanceException extends DomainException
{
    public function __construct(float $requested, float $available)
    {
        parent::__construct(
            "Requested {$requested} days but only {$available} days available.",
            422
        );
    }
}
