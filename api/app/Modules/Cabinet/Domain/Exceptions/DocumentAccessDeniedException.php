<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Domain\Exceptions;

use App\Exceptions\DomainException;

class DocumentAccessDeniedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('You do not have access to this document.', 403);
    }
}
