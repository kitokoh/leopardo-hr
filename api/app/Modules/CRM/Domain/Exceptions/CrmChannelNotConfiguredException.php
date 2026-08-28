<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmChannelNotConfiguredException extends CrmChannelException
{
    public function __construct()
    {
        parent::__construct('Canal actif mais non configuré (token/provider absent).');
    }

    public function errorCode(): string
    {
        return 'CRM_CHANNEL_NOT_CONFIGURED';
    }

    public function httpStatus(): int
    {
        return 503;
    }
}
