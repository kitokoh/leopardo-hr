<?php

namespace App\Exceptions;

class MissingCheckInException extends DomainException
{
    public function __construct()
    {
        parent::__construct('MISSING_CHECK_IN', 422, 'MISSING_CHECK_IN');
    }
}
