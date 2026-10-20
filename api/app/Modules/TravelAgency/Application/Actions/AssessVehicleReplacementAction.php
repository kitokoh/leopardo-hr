<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use Illuminate\Database\ConnectionInterface;

/**
 * #8622 — Contrôle de compatibilité d'un remplacement de véhicule.
 *
 * Le remplacement d'un véhicule après des réservations ne doit JAMAIS
 * déplacer un passager silencieusement ni dupliquer une réservation. Cette
 * action ne fait que CALCULER les conflits (aucune écriture) ; l'appelant
 * refuse le changement si `compatible` est faux.
 *
 * Règles de conflit :
 *
 * - `seat_beyond_capacity` (BLOQUANT) : un siège OCCUPÉ (réservé, vendu) porte
 *   un numéro supérieur à la capacité du nouveau véhicule — le passager se
 *   retrouverait sur une place qui n'existe pas dans la configuration cible.
 * - `blocked_seat_beyond_capacity` (avertissement) : un siège bloqué ou
 *   indisponible au-delà de la capacité ; aucun passager concerné, mais
 *   l'inventaire doit être revu par l'opérateur.
 * - `free_seat_beyond_capacity` (avertissement) : des places libres au-delà de
 *   la capacité cible — commercialisables à tort si elles restent vendues.
 *
 * Aucun conflit bloquant ⇒ `compatible = true`. L'inventaire existant n'est
 * JAMAIS régénéré : les références de siège restent stables, donc les
 * réservations et billets restent rattachés au même numéro.
 */
final class AssessVehicleReplacementAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * Évalue un changement de véhicule sans rien écrire.
     *
     * @return array{
     *     compatible: bool,
     *     current_vehicle_id: int|null,
     *     target_vehicle_id: int|null,
     *     target_capacity: int,
     *     trip_total_seats: int,
     *     conflicts: list<array{
     *         type: string,
     *         seat_number: int,
     *         severity: string,
     *         booking_reference: string|null
     *     }>,
     *     error?: string
     * }
     *
     * Bloquant = siège réservé/vendu au-delà de la capacité ; avertissement =
     * siège libre/bloqué au-delà. Véhicule inconnu → refus fail-closed
     * (`error: vehicle_not_found`).
     */
    public function execute(TravelTrip $trip, ?int $targetVehicleId): array
    {
        return $this->assess($trip, $targetVehicleId);
    }

    /**
     * Évalue un changement de véhicule sans rien écrire (cœur du calcul).
     *
     * @return array{
     *     compatible: bool,
     *     current_vehicle_id: int|null,
     *     target_vehicle_id: int|null,
     *     target_capacity: int,
     *     trip_total_seats: int,
     *     conflicts: list<array{
     *         type: string,
     *         seat_number: int,
     *         severity: string,
     *         booking_reference: string|null
     *     }>,
     *     error?: string
     * }
     */
    public function assess(TravelTrip $trip, ?int $targetVehicleId): array
    {
        $trip->loadMissing('seats');

        $vehicle = $targetVehicleId !== null
            ? TravelVehicle::query()->find($targetVehicleId)
            : null;

        // Véhicule introuvable dans le tenant courant = refus (fail-closed).
        if ($targetVehicleId !== null && $vehicle === null) {
            return [
                'compatible' => false,
                'current_vehicle_id' => $trip->vehicle_id,
                'target_vehicle_id' => $targetVehicleId,
                'target_capacity' => 0,
                'trip_total_seats' => $trip->total_seats,
                'conflicts' => [],
                'error' => 'vehicle_not_found',
            ];
        }

        $capacity = $vehicle instanceof TravelVehicle ? (int) $vehicle->seat_capacity : 0;

        /** @var list<TravelTripSeat> $seats */
        $seats = $trip->seats->sortBy('seat_number')->values()->all();

        $bookingIds = collect($seats)
            ->pluck('booking_id')
            ->filter()
            ->unique()
            ->values();

        /** @var array<int, string> $references */
        $references = $bookingIds->isEmpty()
            ? []
            : TravelBooking::query()
                ->whereIn('id', $bookingIds->all())
                ->pluck('reference', 'id')
                ->all();

        $conflicts = [];

        foreach ($seats as $seat) {
            if ($seat->seat_number <= $capacity) {
                continue;
            }

            $status = $seat->status;

            $occupied = in_array($status, [SeatStatus::RESERVED, SeatStatus::SOLD], true);

            if ($occupied) {
                $type = 'seat_beyond_capacity';
                $severity = 'blocking';
            } elseif ($status === SeatStatus::FREE) {
                $type = 'free_seat_beyond_capacity';
                $severity = 'warning';
            } else {
                $type = 'blocked_seat_beyond_capacity';
                $severity = 'warning';
            }

            $conflicts[] = [
                'type' => $type,
                'seat_number' => (int) $seat->seat_number,
                'severity' => $severity,
                'booking_reference' => $seat->booking_id !== null
                    ? ($references[(int) $seat->booking_id] ?? null)
                    : null,
            ];
        }

        $blocking = array_values(array_filter(
            $conflicts,
            static fn (array $conflict): bool => $conflict['severity'] === 'blocking',
        ));

        return [
            'compatible' => $blocking === [],
            'current_vehicle_id' => $trip->vehicle_id,
            'target_vehicle_id' => $targetVehicleId,
            'target_capacity' => $capacity,
            'trip_total_seats' => $trip->total_seats,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Applique un changement de véhicule déjà évalué comme compatible.
     *
     * Toute la transaction est refusée si un conflit bloquant apparaît entre
     * l'évaluation et l'écriture (deuxième réservation concurrente) : on
     * re-teste SOUS verrou, jamais sur la seule foi de l'évaluation initiale.
     *
     * @return array{compatible: bool, conflicts: list<array<string, mixed>>}
     */
    public function apply(TravelTrip $trip, ?int $targetVehicleId): array
    {
        return $this->db->transaction(function () use ($trip, $targetVehicleId): array {
            /** @var TravelTrip $locked */
            $locked = TravelTrip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();

            $assessment = $this->assess($locked, $targetVehicleId);

            if ($assessment['compatible'] !== true) {
                return $assessment;
            }

            // Aucune régénération d'inventaire : les sièges et leurs
            // réservations restent inchangés, seule l'affectation bouge.
            $locked->forceFill(['vehicle_id' => $targetVehicleId])->save();

            $trip->setRawAttributes($locked->getAttributes(), true);

            return $assessment;
        });
    }
}
