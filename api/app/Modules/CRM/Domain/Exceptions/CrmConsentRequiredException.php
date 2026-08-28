<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmConsentRequiredException extends CrmChannelException
{
    public function __construct()
    {
        parent::__construct('Consentement de communication requis pour ce contact/canal/finalité.');
    }

    public function errorCode(): string
    {
        return 'CRM_CONSENT_REQUIRED';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
