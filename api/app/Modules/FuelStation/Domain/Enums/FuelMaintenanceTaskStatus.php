<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * #5804 — Statuts d'une tâche de maintenance (FUEL-010).
 */
enum FuelMaintenanceTaskStatus: string
{
    case Open = 'open';

    case InProgress = 'in_progress';

    case Completed = 'completed';

    case Cancelled = 'cancelled';
}
