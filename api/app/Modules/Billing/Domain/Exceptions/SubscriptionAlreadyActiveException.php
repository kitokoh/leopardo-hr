<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Exceptions;

use App\Exceptions\DomainException;

class SubscriptionAlreadyActiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Company already has an active subscription.', 422);
    }
}
