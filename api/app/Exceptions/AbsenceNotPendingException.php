<?php

namespace App\Exceptions;

class AbsenceNotPendingException extends DomainException
{
    public function __construct()
    {
        parent::__construct("Cette absence n'est pas en attente d'approbation.", 422, 'ABSENCE_NOT_PENDING');
    }
}
