<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmQuotaExceededException extends CrmChannelException
{
    public function __construct()
    {
        parent::__construct('Quota mensuel du canal dépassé pour ce tenant.');
    }

    public function errorCode(): string
    {
        return 'CRM_QUOTA_EXCEEDED';
    }

    public function httpStatus(): int
    {
        return 429;
    }
}
