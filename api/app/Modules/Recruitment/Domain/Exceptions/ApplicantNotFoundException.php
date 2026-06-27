<?php

namespace App\Modules\Recruitment\Domain\Exceptions;

use App\Exceptions\DomainException;

class ApplicantNotFoundException extends DomainException
{
    public function __construct(string $id)
    {
        parent::__construct("Applicant [{$id}] not found.", 404);
    }

    public function errorCode(): string
    {
        return 'APPLICANT_NOT_FOUND';
    }
}
