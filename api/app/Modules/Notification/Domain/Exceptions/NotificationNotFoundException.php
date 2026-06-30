<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Exceptions;

use App\Exceptions\DomainException;

class NotificationNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct("Notification #{$id} not found.", 404);
    }
}
