<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Domain\Exceptions;

use RuntimeException;

class GpsConsentMissingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('GPS consent is required before enabling automatic geo check-in.');
    }
}
