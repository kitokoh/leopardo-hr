<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Exceptions;

use App\Exceptions\DomainException;

class PlatformAccessDeniedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Platform admin access required.', 403);
    }
}
