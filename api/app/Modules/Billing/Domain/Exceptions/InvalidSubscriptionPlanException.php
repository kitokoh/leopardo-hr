<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Exceptions;

use App\Exceptions\DomainException;

class InvalidSubscriptionPlanException extends DomainException
{
    public function __construct(string $plan)
    {
        parent::__construct("Invalid subscription plan: {$plan}.", 422);
    }
}
