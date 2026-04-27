<?php

namespace App\Exceptions;

class AlreadyCheckedInException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Vous avez déjà pointé votre arrivée aujourd\'hui.', 422, 'ALREADY_CHECKED_IN');
    }
}
