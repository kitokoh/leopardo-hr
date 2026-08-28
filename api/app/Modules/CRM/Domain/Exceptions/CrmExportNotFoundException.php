<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmExportNotFoundException extends CrmExportException
{
    public function __construct()
    {
        parent::__construct('Job d\'export CRM introuvable dans le tenant courant.');
    }

    public function errorCode(): string
    {
        return 'CRM_EXPORT_NOT_FOUND';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
