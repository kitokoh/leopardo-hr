<?php

namespace App\Exceptions;

use Illuminate\Support\Carbon;

class AccountLockedException extends DomainException
{
    public function __construct(Carbon $until)
    {
        parent::__construct(
            "Compte verrouillé jusqu'à " . $until->toIso8601String(),
            423,
            'ACCOUNT_LOCKED'
        );
    }
}
