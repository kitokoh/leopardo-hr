<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Domain\ValueObjects\SeatLayout;

/**
 * #8653 — contrôle de COHÉRENCE entre la disposition d'un véhicule et
 * l'inventaire des sièges.
 *
 * Le contrôle est en LECTURE SEULE : il n'écrit rien et ne corrige rien. Une
 * disposition incohérente est REFUSÉE explicitement (422 côté API) plutôt que
 * silencieusement rabotée — une correction implicite masquerait une erreur de
 * saisie du responsable et produirait un plan de sièges qui ne correspond pas
 * au véhicule.
 *
 * Deux familles de contrôles :
 *   1. cohérence INTERNE de la disposition (colonnes, couloir, sièges absents) ;
 *   2. cohérence avec l'INVENTAIRE EXISTANT : rendre absent un siège déjà
 *      attribué à une réservation sur un trajet utilisant ce véhicule est
 *      refusé, avec la liste des sièges en conflit.
 */
final class ValidateSeatLayoutCoherenceAction
{
    /**
     * @param  array<string, mixed>|null  $layout  disposition soumise (null = véhicule non configuré)
     * @param  int  $capacity  capacité déclarée du véhicule
     * @param  TravelVehicle|null  $vehicle  véhicule existant (mise à jour) — active le contrôle d'inventaire
     * @return array{error: string, message: string, details?: array<string, mixed>}|null `null` = cohérent
     */
    public function execute(?array $layout, int $capacity, ?TravelVehicle $vehicle = null): ?array
    {
        // Un véhicule non configuré reste accepté : aucune géométrie à valider.
        if ($layout === null) {
            return null;
        }

        $perRow = $layout['seats_per_row'] ?? null;
        if (! is_numeric($perRow)) {
            return $this->error('invalid_seats_per_row', 'seats_per_row est obligatoire et doit etre un entier.');
        }

        $perRow = (int) $perRow;
        if ($perRow < SeatLayout::MIN_SEATS_PER_ROW || $perRow > SeatLayout::MAX_SEATS_PER_ROW) {
            return $this->error(
                'invalid_seats_per_row',
                'seats_per_row doit etre compris entre '.SeatLayout::MIN_SEATS_PER_ROW.' et '.SeatLayout::MAX_SEATS_PER_ROW.'.',
            );
        }

        $aisle = $layout['aisle_after'] ?? null;
        if ($aisle !== null) {
            if (! is_numeric($aisle)) {
                return $this->error('invalid_aisle_after', 'aisle_after doit etre un entier ou null.');
            }

            $aisle = (int) $aisle;
            if ($aisle < 1 || $aisle >= $perRow) {
                return $this->error(
                    'invalid_aisle_after',
                    'aisle_after doit etre compris entre 1 et seats_per_row - 1.',
                );
            }
        }

        $absentRaw = $layout['absent_seats'] ?? [];
        if (! is_array($absentRaw)) {
            return $this->error('invalid_absent_seats', 'absent_seats doit etre une liste de numeros de siege.');
        }

        $absent = [];
        foreach ($absentRaw as $seat) {
            if (! is_numeric($seat)) {
                return $this->error('invalid_absent_seats', 'absent_seats doit contenir des entiers.');
            }

            $seat = (int) $seat;

            if ($seat < 1 || $seat > $capacity) {
                return $this->error(
                    'absent_seat_out_of_range',
                    'Un siege absent doit etre un numero de siege valide du vehicule (1..'.$capacity.').',
                    ['seat_number' => $seat, 'capacity' => $capacity],
                );
            }

            if (in_array($seat, $absent, true)) {
                return $this->error(
                    'duplicate_absent_seats',
                    'absent_seats ne peut pas contenir deux fois le meme numero de siege.',
                    ['seat_number' => $seat],
                );
            }

            $absent[] = $seat;
        }

        // Contrôle d'inventaire : uniquement quand on modifie un véhicule
        // existant. Rendre absent un siège déjà rattaché à une réservation
        // laisserait un passager sur un siège inexistant — refus explicite.
        if ($vehicle !== null && $absent !== []) {
            $conflicts = $this->conflictingSeatNumbers($vehicle, $absent);

            if ($conflicts !== []) {
                return $this->error(
                    'layout_conflicts_with_existing_seats',
                    'Des sieges deja attribues a des reservations ne peuvent pas devenir absents.',
                    ['seats' => $conflicts],
                );
            }
        }

        return null;
    }

    /**
     * Numéros de sièges déjà attribués (réservés ou vendus) sur les trajets
     * utilisant ce véhicule, parmi les sièges qu'on veut rendre absents.
     *
     * @param  list<int>  $absent
     * @return list<int>
     */
    private function conflictingSeatNumbers(TravelVehicle $vehicle, array $absent): array
    {
        $tripIds = TravelTrip::query()
            ->where('vehicle_id', $vehicle->id)
            ->pluck('id');

        if ($tripIds->isEmpty()) {
            return [];
        }

        return array_values(
            TravelTripSeat::query()
                ->whereIn('trip_id', $tripIds->all())
                ->whereIn('seat_number', $absent)
                ->whereNotNull('booking_id')
                ->orderBy('seat_number')
                ->pluck('seat_number')
                ->unique()
                ->map(static fn ($seat): int => (int) $seat)
                ->all()
        );
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{error: string, message: string, details?: array<string, mixed>}
     */
    private function error(string $error, string $message, array $details = []): array
    {
        $payload = ['error' => $error, 'message' => $message];

        if ($details !== []) {
            $payload['details'] = $details;
        }

        return $payload;
    }
}
