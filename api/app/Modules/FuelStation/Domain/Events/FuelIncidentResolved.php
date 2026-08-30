<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Events;

use App\Modules\FuelStation\Domain\Models\FuelIncident;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Émis à la résolution d'un incident (FUEL-010, #5804).
 *
 * Contrat notifications (FUEL-019) : résolution notifiée au reporter/à
 * l'assigné sans exposition PII (mêmes règles que FuelIncidentReported).
 */
class FuelIncidentResolved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FuelIncident $incident,
    ) {}
}
