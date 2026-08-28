<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmExportExpiredException extends CrmExportException
{
    public function __construct()
    {
        parent::__construct('Export CRM expiré — générer un nouvel export.');
    }

    public function errorCode(): string
    {
        return 'CRM_EXPORT_EXPIRED';
    }

    public function httpStatus(): int
    {
        return 410;
    }
}
