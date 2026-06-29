<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Domain\Exceptions;

use App\Exceptions\DomainException;

class VehicleNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct("Vehicle #{$id} not found.", 404);
    }
}
