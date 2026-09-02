<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Application\Actions\CreateBookingAction;
use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;

/**
 * TRAVEL-704 (#6091) — Synchronisation offline mobile (file idempotente).
 *
 * L'app agent envoie ses opérations effectuées HORS LIGNE ; le serveur les
 * rejoue IDEMPOTEMENT (clés client conservées → un rejeu ne crée jamais de
 * doublon). Réponse par opération : `created` | `duplicate` | `error`.
 */
final class TravelMobileSyncService
{
    public function __construct(private readonly CreateBookingAction $createBooking) {}

    /**
     * @param  list<array{type: string, payload: array<string, mixed>, idempotency_key: string}>  $operations
     * @return list<array{type: string, status: string, booking_reference?: string, error?: string}>
     */
    public function sync(Employee $actor, array $operations): array
    {
        $results = [];

        foreach (array_slice($operations, 0, 50) as $operation) {
            $type = (string) $operation['type'];
            $key = (string) $operation['idempotency_key'];
            $payload = $operation['payload'];

            if ($key === '' || $payload === []) {
                $results[] = ['type' => $type, 'status' => 'error', 'error' => 'opération invalide (clé ou payload manquant)'];

                continue;
            }

            if ($type === 'booking.create') {
                $results[] = $this->syncBookingCreate($actor, $payload, $key);

                continue;
            }

            $results[] = ['type' => $type, 'status' => 'error', 'error' => 'type d\'opération inconnu'];
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string, status: string, booking_reference?: string, error?: string}
     */
    private function syncBookingCreate(Employee $actor, array $payload, string $key): array
    {
        try {
            $tripId = isset($payload['trip_id']) ? (int) $payload['trip_id'] : 0;
            $passengers = is_array($payload['passengers'] ?? null) ? $payload['passengers'] : [];

            if ($tripId <= 0 || $passengers === []) {
                return ['type' => 'booking.create', 'status' => 'error', 'error' => 'payload incomplet'];
            }

            /** @var TravelTrip $trip */
            $trip = TravelTrip::query()->findOrFail($tripId);

            if ($trip->company_id !== $actor->company_id) {
                return ['type' => 'booking.create', 'status' => 'error', 'error' => 'trajet hors tenant'];
            }

            $booking = $this->createBooking->execute(
                trip: $trip,
                passengers: $passengers,
                source: BookingSource::PHONE,
                actor: $actor,
                idempotencyKey: $key,
                contactEmail: isset($payload['contact_email']) ? (string) $payload['contact_email'] : null,
                contactPhone: isset($payload['contact_phone']) ? (string) $payload['contact_phone'] : null,
                notifyConsent: (bool) ($payload['notify_consent'] ?? false),
            );

            $wasDuplicate = $booking->wasRecentlyCreated === false;

            return [
                'type' => 'booking.create',
                'status' => $wasDuplicate ? 'duplicate' : 'created',
                'booking_reference' => $booking->reference,
            ];
        } catch (\Throwable $e) {
            return ['type' => 'booking.create', 'status' => 'error', 'error' => $e->getMessage()];
        }
    }
}
