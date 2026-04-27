<?php

namespace App\Exceptions;

class InvalidCredentialsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Identifiants incorrects. Veuillez réessayer.', 401, 'INVALID_CREDENTIALS');
    }
}
