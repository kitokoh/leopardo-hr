<?php

declare(strict_types=1);

namespace App\Modules\Training\Domain\Exceptions;

use App\Exceptions\DomainException;

class AlreadyEnrolledException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Employee is already enrolled in this training session.', 422);
    }
}
