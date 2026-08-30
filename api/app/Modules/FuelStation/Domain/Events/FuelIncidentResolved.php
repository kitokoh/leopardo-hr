<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Events;

use App\Modules\FuelStation\Domain\Models\FuelIncident;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Émis quand un incident passe à `resolved` (FUEL-010, #5804).
 *
 * Consommé par le contrat Accounting (FUEL-015) et les notifications
 * (FUEL-019, levée d'alerte).
 */
class FuelIncidentResolved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FuelIncident $incident,
    ) {}
}
