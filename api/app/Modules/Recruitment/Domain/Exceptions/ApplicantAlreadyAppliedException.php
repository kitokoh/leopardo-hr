<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Exceptions;

use App\Exceptions\DomainException;

class ApplicantAlreadyAppliedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('A candidate with this email has already applied for this position.', 409);
    }

    public function errorCode(): string
    {
        return 'ALREADY_APPLIED';
    }
}
