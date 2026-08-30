<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-316 (#6046) — Émission des billets d'une réservation confirmée.
 *
 * Crée un billet nominatif par passager (numéro #GV-…, code de validation
 * haché, validité = trajet du jour). Le code de validation EN CLAIR n'est
 * retourné qu'ici (QR) et n'est jamais persisté. La génération PDF est
 * traitée séparément (TRAVEL-412) via le contrat documents. Idempotent :
 * les passagers déjà pourvus d'un billet ne sont pas ré-émis.
 *
 * @return list<TravelTicket>
 */
final class IssueTicketsAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    /**
     * @return list<TravelTicket>
     */
    public function execute(TravelBooking $booking, Employee $actor): array
    {
        if ($booking->status !== BookingStatus::CONFIRMED) {
            abort(422, 'Seule une réservation confirmée peut émettre des billets.');
        }

        $booking->load('passengers', 'trip');

        $tickets = DB::transaction(function () use ($booking): array {
            $tickets = [];

            foreach ($booking->passengers as $passenger) {
                // Déjà émis (rejeu) : on ne ré-émet jamais un passager.
                if (TravelTicket::query()->where('passenger_id', $passenger->id)->exists()) {
                    continue;
                }

                $ticket = TravelTicket::query()->create([
                    'booking_id' => $booking->id,
                    'passenger_id' => $passenger->id,
                    'status' => TicketStatus::ISSUED,
                    'issued_at' => now(),
                    'valid_from' => now(),
                    'valid_until' => $booking->trip?->departure_date?->endOfDay(),
                ]);

                // Le code en clair (QR) n'est jamais persisté — seul le hash.
                $ticket->issueValidationCode();
                $ticket->save();

                $tickets[] = $ticket;
            }

            return $tickets;
        });

        foreach ($tickets as $ticket) {
            $this->outbox->publish($booking->company_id, 'travel.ticket.issued.v1', [
                'ticket_number' => $ticket->ticket_number,
                'booking_reference' => $booking->reference,
                'passenger_id' => $ticket->passenger_id,
                'issued_at' => $ticket->issued_at?->toIso8601String(),
            ]);
        }

        return $tickets;
    }
}
