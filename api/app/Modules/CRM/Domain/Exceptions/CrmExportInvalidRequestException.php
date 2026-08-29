<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmExportInvalidRequestException extends CrmExportException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'CRM_EXPORT_INVALID_REQUEST';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
