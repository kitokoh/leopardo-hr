<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

final class FuelStationDependencyMissingException extends FuelStationException
{
    /** @param array<int, string> $missing */
    public function __construct(private readonly array $missing)
    {
        parent::__construct('Modules de base manquants pour activer FuelStation : '.implode(', ', $missing));
    }

    public function errorCode(): string
    {
        return 'FUEL_DEPENDENCIES_MISSING';
    }

    /** @return array<int, string> */
    public function missingDependencies(): array
    {
        return $this->missing;
    }
}
