<?php

namespace App\Exceptions;

class CompanyNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct('COMPANY_NOT_FOUND', 403, 'COMPANY_NOT_FOUND');
    }
}
