<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Exceptions;

use App\Exceptions\DomainException;

class OnboardingStepNotFoundException extends DomainException
{
    public function __construct(string $stepKey)
    {
        parent::__construct("Onboarding step '{$stepKey}' not found.", 404);
    }
}
