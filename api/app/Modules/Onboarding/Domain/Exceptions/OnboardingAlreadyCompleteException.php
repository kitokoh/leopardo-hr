<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Exceptions;

use App\Exceptions\DomainException;

class OnboardingAlreadyCompleteException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Onboarding is already complete for this company.', 422);
    }
}
