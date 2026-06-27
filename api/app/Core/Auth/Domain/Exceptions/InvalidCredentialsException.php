<?php

namespace App\Core\Auth\Domain\Exceptions;

class InvalidCredentialsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Identifiants incorrects. Veuillez réessayer.', 401, 'INVALID_CREDENTIALS');
    }
}
