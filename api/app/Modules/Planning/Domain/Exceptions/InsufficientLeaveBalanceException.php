<?php

declare(strict_types=1);

namespace App\Modules\Planning\Domain\Exceptions;

use App\Exceptions\DomainException;

class InsufficientLeaveBalanceException extends DomainException
{
    public function __construct(string $message = 'Solde de congés insuffisant.')
    {
        parent::__construct($message, 422);
    }

    public function errorCode(): string
    {
        return 'INSUFFICIENT_LEAVE_BALANCE';
    }
}
