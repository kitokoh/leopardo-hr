<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * #5803 — Type de mouvement de stock (FUEL-009).
 */
enum FuelStockMovementType: string
{
    case Delivery = 'delivery';

    case Sale = 'sale';

    case Adjustment = 'adjustment';

    case Opening = 'opening';

    case Closing = 'closing';
}
