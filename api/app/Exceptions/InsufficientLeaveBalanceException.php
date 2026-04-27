<?php

namespace App\Exceptions;

class InsufficientLeaveBalanceException extends DomainException
{
    public function __construct(float $available, float $requested)
    {
        parent::__construct(
            "Solde de congés insuffisant. Solde disponible : {$available} jours, demandé : {$requested} jours.",
            422,
            'INSUFFICIENT_LEAVE_BALANCE'
        );
    }
}
