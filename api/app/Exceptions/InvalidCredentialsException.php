<?php

namespace App\Exceptions;

class InvalidCredentialsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('INVALID_CREDENTIALS', 401, 'INVALID_CREDENTIALS');
    }
}
