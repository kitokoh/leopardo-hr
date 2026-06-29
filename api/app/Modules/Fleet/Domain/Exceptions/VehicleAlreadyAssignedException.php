<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Domain\Exceptions;

use App\Exceptions\DomainException;

class VehicleAlreadyAssignedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Vehicle is already assigned to a driver.', 422);
    }
}
