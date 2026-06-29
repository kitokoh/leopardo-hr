<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Domain\Exceptions;

use App\Exceptions\DomainException;

class CameraNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct("Camera #{$id} not found.", 404);
    }
}
