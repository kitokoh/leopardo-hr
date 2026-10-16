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
        // #8539 — le statut est relu DANS la transaction, sous verrou de ligne
        // (`lockForUpdate`) : deux contrôles simultanés du même billet ne
        // peuvent plus lire `issued` tous les deux. Le premier gagne, le
        // second constate `checked_in` et repart en idempotence — sans
        // écraser l'horodatage ni l'opérateur du premier contrôle.
        $checkedIn = DB::transaction(function () use ($ticket, $actor): TravelTicket {
            /** @var TravelTicket $locked */
            $locked = TravelTicket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === TicketStatus::CHECKED_IN) {
                return $locked;
            }

            if ($locked->status !== TicketStatus::ISSUED) {
                abort(422, 'Ce billet ne peut pas etre enregistre.');
            }

            $locked->forceFill([
                'status' => TicketStatus::CHECKED_IN,
                'checked_in_at' => now(),
                'checked_in_by_user_id' => $actor->id,
            ])->save();

            return $locked;
        });

        // L'evenement n'est publie QUE si CE controle a reellement enregistre
        // le billet : un rejeu idempotent ne re-emet jamais l'evenement.
        if ($checkedIn->wasChanged('status')) {
            $this->outbox->publish($checkedIn->company_id, 'travel.ticket.checked_in.v1', [
                'ticket_number' => $checkedIn->ticket_number,
                'booking_id' => $checkedIn->booking_id,
                'checked_in_by' => $actor->id,
                'checked_in_at' => $checkedIn->checked_in_at?->toIso8601String(),
            ]);
        }

        return $checkedIn;
    }
}
