<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Events;

use App\Modules\FuelStation\Domain\Models\FuelIncident;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Émis à la création d'un incident (FUEL-010, #5804).
 *
 * Contrat notifications (FUEL-019) : le listener ne doit JAMAIS exposer de
 * PII — l'événement ne transporte que des identifiants + priorité + titre
 * d'équipement, jamais de données personnelles.
 */
class FuelIncidentReported
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FuelIncident $incident,
    ) {}
}
