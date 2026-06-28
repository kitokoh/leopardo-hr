<?php

namespace App\Core\Auth\Domain\Exceptions;

class AccountSuspendedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Votre compte a été suspendu. Contactez votre administrateur.', 403, 'ACCOUNT_SUSPENDED');
    }
}
