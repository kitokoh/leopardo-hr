<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

final class CrmAutomationInvalidException extends CrmAutomationException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'CRM_AUTOMATION_INVALID';
    }
}
