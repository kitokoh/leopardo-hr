<?php

namespace App\Exceptions;

class MissingCheckInException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Aucun pointage d\'arrivée trouvé pour aujourd\'hui.', 422, 'MISSING_CHECK_IN');
    }
}
