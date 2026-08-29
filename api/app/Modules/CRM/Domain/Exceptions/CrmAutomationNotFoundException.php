<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmAutomationNotFoundException extends CrmAutomationException
{
    public function __construct()
    {
        parent::__construct('Automatisation CRM introuvable dans le tenant courant.');
    }

    public function errorCode(): string
    {
        return 'CRM_AUTOMATION_NOT_FOUND';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
