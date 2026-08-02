<?php

declare(strict_types=1);

namespace App\Modules\Planning\Domain\Exceptions;

use App\Exceptions\DomainException;

class AbsenceDateConflictException extends DomainException
{
    public function __construct(string $message = 'Une absence existe déjà pour cette période.')
    {
        parent::__construct($message, 422);
    }

    public function errorCode(): string
    {
        return 'ABSENCE_DATE_CONFLICT';
    }
}
