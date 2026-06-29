<?php

declare(strict_types=1);

namespace App\Modules\Growth\Domain\Exceptions;

use App\Exceptions\DomainException;

class PartnerNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct("Partner #{$id} not found.", 404);
    }
}
