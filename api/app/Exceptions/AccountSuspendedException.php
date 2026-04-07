<?php

namespace App\Exceptions;

class AccountSuspendedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('ACCOUNT_SUSPENDED', 403, 'ACCOUNT_SUSPENDED');
    }
}
