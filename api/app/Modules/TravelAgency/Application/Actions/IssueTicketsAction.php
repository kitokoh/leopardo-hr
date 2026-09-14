<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Infrastructure\Services\LoyaltyPointsService;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-316 (#6046) — Emission des billets d'une reservation confirmee.
 *
 * Cree un billet nominatif par passager (numero #GV-…, code de validation
 * hache, validite = trajet du jour). #7394 : le code de validation EN CLAIR
 * est desormais DELIVRE au passager — porte par `issuedValidationCode` sur le
 * billet retourne (expose une seule fois par la reponse d'emission), imprime
 * sur l'e-billet PDF, et jamais relisible depuis la base (seul le hash y
 * vit). La generation PDF est traitee separement (TRAVEL-412) via le contrat
 * documents. Idempotent : les passagers deja pourvus d'un billet ne sont pas
 * re-emis — un rejeu ne re-delivre donc aucun code.
 *
 * @return list<TravelTicket>
 */
final class IssueTicketsAction
{
    public function __construct(
        private readonly TravelOutboxPublisher $outbox,
        private readonly LoyaltyPointsService $loyalty,
    ) {}

    /**
     * @return list<TravelTicket>
     */
    public function execute(TravelBooking $booking, Employee $actor): array
    {
        if ($booking->status !== BookingStatus::CONFIRMED) {
            abort(422, 'Seule une reservation confirmee peut emettre des billets.');
        }

        $booking->load('passengers', 'trip');

        $tickets = DB::transaction(function () use ($booking): array {
            $tickets = [];

            foreach ($booking->passengers as $passenger) {
                // Deja emis (rejeu) : on ne re-emet jamais un passager.
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

                // #7394 : le code en clair n'est jamais persiste (seul le hash
                // l'est, en base) — il est porte par la propriete transiente
                // `issuedValidationCode` pour etre delivre UNE fois.
                $ticket->issuedValidationCode = $ticket->issueValidationCode();
                $ticket->save();

                $tickets[] = $ticket;
            }

            return $tickets;
        });

        foreach ($tickets as $ticket) {
            // TRAVEL-811 (#6101) : crédit fidélité une seule fois par billet
            // (opt-in requis, géré par LoyaltyPointsService).
            $priceMinor = $ticket->passenger?->unit_price_minor;
            if ($priceMinor !== null) {
                $this->loyalty->earnForTicket($ticket, (int) $priceMinor);
            }

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
