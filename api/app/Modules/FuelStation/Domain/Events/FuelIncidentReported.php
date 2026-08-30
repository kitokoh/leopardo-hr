<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Events;

use App\Modules\FuelStation\Domain\Models\FuelIncident;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Émis à la création d'un incident FuelStation (FUEL-010, #5804).
 *
 * Notifications (FUEL-019) : alerte incident (sévérité ≥ high → canal
 * prioritaire) sans exposition PII.
 */
class FuelIncidentReported
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FuelIncident $incident,
    ) {}
}
