<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-317 (#6047) — Check-in / embarquement d'un billet.
 *
 * issued → checked_in : horodatage + operateur, evenement outbox
 * `travel.ticket.checked_in.v1` apres commit. Un billet deja enregistre
 * est idempotent ; un billet annule (void) est refuse.
 */
final class CheckInTicketAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    public function execute(TravelTicket $ticket, Employee $actor): TravelTicket
    {
        if ($ticket->status === TicketStatus::CHECKED_IN) {
            return $ticket;
        }

        if ($ticket->status !== TicketStatus::ISSUED) {
            abort(422, 'Ce billet ne peut pas etre enregistre.');
        }

        DB::transaction(function () use ($ticket, $actor): void {
            $ticket->forceFill([
                'status' => TicketStatus::CHECKED_IN,
                'checked_in_at' => now(),
                'checked_in_by_user_id' => $actor->id,
            ])->save();
        });

        $this->outbox->publish($ticket->company_id, 'travel.ticket.checked_in.v1', [
            'ticket_number' => $ticket->ticket_number,
            'booking_id' => $ticket->booking_id,
            'checked_in_by' => $actor->id,
            'checked_in_at' => now()->toIso8601String(),
            'notification_intent' => 'travel.ticket.checked_in',
            'consent' => false, // Opt-in explicite requis via contrat CRM client (TRAVEL-416).
        ]);

        return $ticket->refresh();
    }
}
