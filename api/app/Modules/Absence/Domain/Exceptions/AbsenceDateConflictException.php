<?php

declare(strict_types=1);

namespace App\Modules\Absence\Domain\Exceptions;

use App\Exceptions\DomainException;

class AbsenceDateConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct('An absence already exists for the requested period.', 422);
    }
}
