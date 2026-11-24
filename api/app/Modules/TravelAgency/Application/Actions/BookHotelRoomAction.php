<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelHotelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelHotelRoom;
use App\Modules\TravelAgency\Domain\Models\TravelHotelRoomInventory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;

/**
 * M4 — Réservation d'une chambre d'hôtel sur un intervalle de dates.
 *
 * Invariant de non-surréservation, sur le modèle de `CreateBookingAction`
 * (stock de sièges) :
 *
 * 1. Idempotence d'abord, HORS transaction : un rejeu avec la même clé
 *    renvoie la réservation existante sans toucher à l'inventaire.
 * 2. Dans la transaction, les lignes d'inventaire de TOUTES les nuits sont
 *    verrouillées (`FOR UPDATE`) : deux réservations concurrentes sur la même
 *    chambre sont sérialisées, la seconde voit le stock déjà décrémenté.
 * 3. Une nuit manquante est créée à la volée (capacité 1, ouverte) : une
 *    chambre nouvellement cataloguée est vendable sans pré-remplissage.
 * 4. Si une seule nuit est insuffisante → `409`, transaction annulée, aucune
 *    nuit n'est consommée (pas de réservation partielle).
 */
final class BookHotelRoomAction
{
    // #6568 — la facade `DB` est reservee a Interfaces/Infrastructure : la
    // couche Application depend du gestionnaire de base injecte.
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{check_in: string, check_out: string, rooms?: int|null}  $data
     */
    public function execute(
        TravelHotelRoom $room,
        Employee $actor,
        string $idempotencyKey,
        array $data,
    ): TravelHotelBooking {
        $existing = TravelHotelBooking::query()
            ->where('room_id', $room->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof TravelHotelBooking) {
            return $existing;
        }

        $checkIn = Carbon::parse($data['check_in'])->startOfDay();
        $checkOut = Carbon::parse($data['check_out'])->startOfDay();
        $nights = (int) $checkIn->diffInDays($checkOut);
        $rooms = max(1, (int) ($data['rooms'] ?? 1));

        if ($nights < 1) {
            abort(422, __('travel.hotel.departure_after_arrival'));
        }

        return $this->db->transaction(function () use ($room, $checkIn, $checkOut, $nights, $rooms, $idempotencyKey): TravelHotelBooking {
            // Verrouille la chambre : sérialise les créations concurrentes.
            TravelHotelRoom::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

            $lastNight = $checkOut->copy()->subDay()->toDateString();

            /** @var \Illuminate\Support\Collection<string, TravelHotelRoomInventory> $locked */
            $locked = TravelHotelRoomInventory::query()
                ->forRoomRange($room->id, $checkIn->toDateString(), $lastNight)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (TravelHotelRoomInventory $row): string => $row->stay_date->toDateString());

            $cursor = $checkIn->copy();

            while ($cursor->lt($checkOut)) {
                $date = $cursor->toDateString();
                $row = $locked->get($date);

                if (! $row instanceof TravelHotelRoomInventory) {
                    $row = TravelHotelRoomInventory::query()->create([
                        'room_id' => $room->id,
                        'stay_date' => $date,
                        'rooms_total' => 1,
                        'rooms_booked' => 0,
                    ]);
                    $locked->put($date, $row);
                }

                if ($row->remaining() < $rooms) {
                    abort(409, __('travel.hotel.room_unavailable', ['date' => $date]));
                }

                $cursor->addDay();
            }

            // Toutes les nuits sont disponibles : on consomme l'inventaire.
            foreach ($locked as $row) {
                $row->forceFill(['rooms_booked' => $row->rooms_booked + $rooms])->save();
            }

            return TravelHotelBooking::query()->create([
                'room_id' => $room->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'rooms' => $rooms,
                'nights' => $nights,
                'total_amount_minor' => $room->price_per_night_minor * $nights * $rooms,
                'currency' => $room->currency,
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }
}
