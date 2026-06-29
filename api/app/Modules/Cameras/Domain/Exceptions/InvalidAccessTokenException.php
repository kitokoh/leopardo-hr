<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Domain\Exceptions;

use App\Exceptions\DomainException;

class InvalidAccessTokenException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Camera access token is invalid or expired.', 401);
    }
}
