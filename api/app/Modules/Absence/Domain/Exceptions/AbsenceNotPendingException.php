<?php

declare(strict_types=1);

namespace App\Modules\Absence\Domain\Exceptions;

use App\Exceptions\DomainException;

class AbsenceNotPendingException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Only pending absences can be approved or rejected.', 422);
    }
}
