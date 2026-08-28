<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmWebhookSignatureInvalidException extends CrmChannelException
{
    public function __construct()
    {
        parent::__construct('Signature de webhook CRM invalide (fail-closed).');
    }

    public function errorCode(): string
    {
        return 'CRM_WEBHOOK_SIGNATURE_INVALID';
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
