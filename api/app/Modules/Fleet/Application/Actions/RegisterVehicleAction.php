<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\Actions;

use App\Modules\Fleet\Domain\Models\Vehicle;

/**
 * Cas d'usage « enregistrer un vehicule » (BOS-024g, #8218 — BC-24).
 *
 * Extrait de `VehicleController::store` : le company_id vient de la session
 * (jamais du payload client), le reste du payload valide est persiste tel
 * quel. Aucune evolution de comportement ni de contrat d'API.
 */
final class RegisterVehicleAction
{
    /**
     * @param  array<string, mixed>  $payload  Payload valide (VehicleController::store).
     */
    public function execute(string $companyId, array $payload): Vehicle
    {
        /** @var Vehicle $vehicle */
        $vehicle = Vehicle::query()->create(array_merge($payload, ['company_id' => $companyId]));

        return $vehicle;
    }
}
