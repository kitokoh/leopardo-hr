<?php

namespace App\Modules\Planning\Domain\Exceptions;

use App\Exceptions\DomainException;

class AbsenceNotPendingException extends DomainException
{
    public function __construct(string $id)
    {
        parent::__construct("Absence [{$id}] is not in pending status.", 422);
    }

    public function errorCode(): string
    {
        return 'ABSENCE_NOT_PENDING';
    }
}
