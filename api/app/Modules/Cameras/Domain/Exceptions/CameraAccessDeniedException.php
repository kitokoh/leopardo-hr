<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Domain\Exceptions;

use App\Exceptions\DomainException;

class CameraAccessDeniedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('You do not have permission to access this camera.', 403);
    }
}
