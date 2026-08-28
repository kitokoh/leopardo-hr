<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmAutomationEmergencyStoppedException extends CrmAutomationException
{
    public function __construct()
    {
        parent::__construct('Automatisations CRM arrêtées d\'urgence pour ce tenant.');
    }

    public function errorCode(): string
    {
        return 'CRM_AUTOMATION_EMERGENCY_STOPPED';
    }

    public function httpStatus(): int
    {
        return 423;
    }
}
