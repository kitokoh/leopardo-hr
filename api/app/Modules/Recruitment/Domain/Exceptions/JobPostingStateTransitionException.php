<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Exceptions;

use App\Exceptions\DomainException;

class JobPostingStateTransitionException extends DomainException
{
    public function __construct(public readonly string $translationKey)
    {
        parent::__construct("Invalid job posting state transition [{$translationKey}].", 422);
    }

    public function errorCode(): string
    {
        return $this->translationKey;
    }
}
