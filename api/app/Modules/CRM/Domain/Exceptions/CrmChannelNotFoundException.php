<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmChannelNotFoundException extends CrmChannelException
{
    public function __construct()
    {
        parent::__construct('Channel introuvable dans le tenant courant.');
    }

    public function errorCode(): string
    {
        return 'CRM_CHANNEL_NOT_FOUND';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
