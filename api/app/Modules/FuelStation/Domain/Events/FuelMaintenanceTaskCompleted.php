<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Events;

use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Émis quand une tâche de maintenance passe à `done` (FUEL-010, #5804).
 *
 * Consommé par les notifications (FUEL-019).
 */
class FuelMaintenanceTaskCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FuelMaintenanceTask $task,
    ) {}
}
