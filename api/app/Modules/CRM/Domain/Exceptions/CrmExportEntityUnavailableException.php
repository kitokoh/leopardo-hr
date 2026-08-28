<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmExportEntityUnavailableException extends CrmExportException
{
    public function __construct()
    {
        parent::__construct('Entité d\'export CRM indisponible (socle V0 non encore mergé sur cet environnement).');
    }

    public function errorCode(): string
    {
        return 'CRM_EXPORT_ENTITY_UNAVAILABLE';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
