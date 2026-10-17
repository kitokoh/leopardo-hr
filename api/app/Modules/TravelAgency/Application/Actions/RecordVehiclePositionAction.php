<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\TravelVehiclePositionSource;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Domain\Models\TravelVehiclePositionLog;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * #8540 (TRAVEL-OS) — enregistrement de la DERNIÈRE POSITION CONNUE d'un
 * véhicule.
 *
 * Aucun fournisseur GPS n'est intégré : cette action ne fait qu'accepter une
 * position transmise par un dispositif autorisé (boîtier GPS ou application).
 * Elle n'invente jamais de coordonnées — sans transmission, le véhicule reste
 * sans position connue.
 *
 * #8548 — la même transmission alimente désormais l'HISTORIQUE
 * (`travel_vehicle_position_logs`), dans la MÊME transaction que la mise à
 * jour de l'instantané : soit les deux écritures passent, soit aucune. Un
 * instantané sans trace d'historique (ou l'inverse) serait un état
 * incohérent, impossible à auditer.
 *
 * `recorded_at` est l'horodatage de la MESURE : celui transmis par le
 * dispositif s'il est fourni, sinon l'instant de réception. Il n'est jamais
 * antidaté ni deviné.
 */
final class RecordVehiclePositionAction
{
    /**
     * La connexion est INJECTÉE (et non résolue via la façade `DB`) : la
     * façade Laravel est réservée aux couches Interfaces/Infrastructure
     * (garde de pureté de couches, issue #6568).
     */
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function execute(
        TravelVehicle $vehicle,
        float $latitude,
        float $longitude,
        TravelVehiclePositionSource $source,
        ?Carbon $recordedAt = null,
    ): TravelVehicle {
        $measuredAt = $recordedAt ?? Carbon::now();

        return $this->connection->transaction(function () use ($vehicle, $latitude, $longitude, $source, $measuredAt): TravelVehicle {
            $vehicle->forceFill([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'position_source' => $source,
                'position_recorded_at' => $measuredAt,
            ])->save();

            // Historique : une ligne par transmission réellement reçue.
            TravelVehiclePositionLog::query()->create([
                'vehicle_id' => $vehicle->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'source' => $source,
                'recorded_at' => $measuredAt,
            ]);

            return $vehicle->refresh();
        });
    }
}
