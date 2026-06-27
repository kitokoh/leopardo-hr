<?php

namespace App\Modules\Recruitment\Domain\Exceptions;

use App\Exceptions\DomainException;

class JobPostingNotFoundException extends DomainException
{
    public function __construct(string $id)
    {
        parent::__construct("Job posting [{$id}] not found.", 404);
    }

    public function errorCode(): string
    {
        return 'JOB_POSTING_NOT_FOUND';
    }
}
