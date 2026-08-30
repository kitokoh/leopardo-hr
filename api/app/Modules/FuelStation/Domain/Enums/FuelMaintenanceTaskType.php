<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * #5804 — Type de maintenance (FUEL-010).
 */
enum FuelMaintenanceTaskType: string
{
    case Preventive = 'preventive';

    case Corrective = 'corrective';
}
