<?php

namespace App\Exceptions;

class AlreadyCheckedInException extends DomainException
{
    public function __construct()
    {
        parent::__construct('ALREADY_CHECKED_IN', 409, 'ALREADY_CHECKED_IN');
    }
}
