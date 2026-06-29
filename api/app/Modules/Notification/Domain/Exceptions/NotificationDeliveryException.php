<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Exceptions;

use App\Exceptions\DomainException;

class NotificationDeliveryException extends DomainException
{
    public function __construct(string $reason = 'Failed to deliver notification.')
    {
        parent::__construct($reason, 500);
    }
}
