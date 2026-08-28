<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

final class FuelStationManifestInvalidException extends FuelStationException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'FUEL_MANIFEST_INVALID';
    }
}
