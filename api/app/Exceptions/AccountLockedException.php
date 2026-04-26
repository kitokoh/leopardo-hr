<?php

namespace App\Exceptions;

use Illuminate\Support\Carbon;

class AccountLockedException extends DomainException
{
    public function __construct(Carbon $until)
    {
        parent::__construct(
            "Compte verrouille suite a trop de tentatives echouees. Reessayez apres " . $until->setTimezone(config('app.timezone', 'UTC'))->format('H:i:s'),
            423,
            'ACCOUNT_LOCKED'
        );
    }
}
