<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

use RuntimeException;

/**
 * Levée par `FuelShiftService::assertNoOverlap()` (FUEL-005, #5799)
 * lorsqu'un employé est déjà affecté, le même jour, à un shift dont les
 * horaires se recouvrent avec celui demandé.
 */
final class ShiftOverlapException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('SHIFT_OVERLAP');
    }
}
