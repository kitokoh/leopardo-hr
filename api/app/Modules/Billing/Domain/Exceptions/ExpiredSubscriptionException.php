<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Exceptions;

use App\Exceptions\DomainException;

class ExpiredSubscriptionException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Subscription has expired. Please renew to continue.', 403);
    }
}
