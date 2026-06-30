<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Domain\Exceptions;

use App\Exceptions\DomainException;

class DocumentNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct("Document #{$id} not found.", 404);
    }
}
