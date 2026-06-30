<?php

declare(strict_types=1);

namespace App\Modules\Training\Domain\Exceptions;

use App\Exceptions\DomainException;

class CourseNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct("Training course #{$id} not found.", 404);
    }
}
