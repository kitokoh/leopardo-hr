<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * #5804 — Priorité d'une tâche de maintenance (FUEL-010).
 */
enum FuelMaintenanceTaskPriority: string
{
    case Low = 'low';

    case Medium = 'medium';

    case High = 'high';
}
