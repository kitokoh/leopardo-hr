<?php

namespace App\Modules\HR\Domain\Exceptions;

use RuntimeException;

class EmployeeNotFoundException extends RuntimeException
{
    public function __construct(int|string $id)
    {
        parent::__construct("Employee not found: {$id}");
    }
}
