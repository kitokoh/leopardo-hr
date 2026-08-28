<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmExportNotReadyException extends CrmExportException
{
    public function __construct()
    {
        parent::__construct('Export CRM pas encore prêt (traitement en cours) ou échoué.');
    }

    public function errorCode(): string
    {
        return 'CRM_EXPORT_NOT_READY';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
