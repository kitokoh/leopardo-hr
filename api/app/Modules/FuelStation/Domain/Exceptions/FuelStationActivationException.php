<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

final class FuelStationActivationException extends FuelStationException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'FUEL_ACTIVATION_FAILED';
    }

    public function httpStatus(): int
    {
        return 500;
    }
}
