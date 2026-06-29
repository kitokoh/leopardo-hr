<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Domain\Exceptions;

use App\Exceptions\DomainException;

class MaintenanceRequiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Vehicle requires maintenance before assignment.', 422);
    }
}
