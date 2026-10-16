<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;

/**
 * #8538 (TRAVEL-OS) — VÉRIFICATION d'un billet (lecture seule).
 *
 * Distincte de la CONSOMMATION ({@see CheckInTicketAction}) : vérifier un
 * billet ne le rend jamais inutilisable. L'agent de contrôle scanne le QR
 * (ou saisit la référence + le code à la main si la caméra ne fonctionne
 * pas), le serveur confirme l'authenticité et renvoie le statut.
 *
 * Le billet est retrouvé par `reference` + `code de contrôle` : le code est
 * comparé au hash stocké en temps constant
 * ({@see TravelTicket::validationCodeMatches()}). Un code invalide et une
 * référence inexistante produisent la MÊME réponse (404) — aucune fuite
 * d'information sur l'existence d'une réservation.
 */
final class VerifyTicketAction
{
    public const STATUS_VALID = 'valid';
    public const STATUS_ALREADY_USED = 'already_used';
    public const STATUS_VOID = 'void';
    public const STATUS_EXPIRED = 'expired';

    /**
     * @return array{booking: TravelBooking, ticket: TravelTicket, status: string}
     */
    public function execute(string $reference, string $code, Employee $actor): array
    {
        $booking = TravelBooking::query()
            ->where('reference', $reference)
            ->where('company_id', $actor->company_id)
            ->with([
                'trip.route.originCity',
                'trip.route.destinationCity',
                'tickets.passenger',
            ])
            ->first();

        if (! $booking instanceof TravelBooking) {
            abort(404);
        }

        $ticket = $booking->tickets->first(
            fn (TravelTicket $candidate): bool => $candidate->validationCodeMatches($code)
        );

        if (! $ticket instanceof TravelTicket) {
            abort(404, 'Code de validation invalide.');
        }

        return [
            'booking' => $booking,
            'ticket' => $ticket,
            'status' => $this->resolveStatus($ticket),
        ];
    }

    /**
     * Statut opérationnel du billet, dans l'ordre de priorité métier :
     * annulé > déjà utilisé > expiré > valide.
     */
    public function resolveStatus(TravelTicket $ticket): string
    {
        if ($ticket->status === TicketStatus::VOID) {
            return self::STATUS_VOID;
        }

        if ($ticket->status === TicketStatus::CHECKED_IN) {
            return self::STATUS_ALREADY_USED;
        }

        if ($ticket->valid_until !== null && $ticket->valid_until->isPast()) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_VALID;
    }
}
