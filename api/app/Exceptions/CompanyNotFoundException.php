<?php

namespace App\Exceptions;

class CompanyNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Entreprise introuvable ou accès non autorisé.', 403, 'COMPANY_NOT_FOUND');
    }
}
