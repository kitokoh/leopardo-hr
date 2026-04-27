<?php

namespace App\Exceptions;

class AbsenceDateConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Les dates de cette absence chevauchent une absence existante.', 422, 'ABSENCE_DATE_CONFLICT');
    }
}
