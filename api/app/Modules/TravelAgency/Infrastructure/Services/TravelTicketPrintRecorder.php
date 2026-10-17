<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTicketPrintEvent;
use Illuminate\Support\Facades\DB;

/**
 * #8544 (TRAVEL-OS) — persistance de la trace d'impression d'un billet.
 *
 * Le couple transaction + verrou de ligne vit ICI, en Infrastructure :
 * l'Application ({@see \App\Modules\TravelAgency\Application\Actions\RecordTicketPrintAction})
 * ne touche pas aux façades Laravel (garde CI #6568,
 * `dev-hub/tools/check-layer-purity.sh`).
 *
 * `lockForUpdate()` verrouille la LIGNE du billet (un verrou d'agrégat est
 * refusé par PostgreSQL), ce qui sérialise deux impressions simultanées du
 * même billet : le `reprint_number` reste strictement croissant, sans trou ni
 * doublon.
 */
final class TravelTicketPrintRecorder
{
    public function record(
        TravelTicket $ticket,
        Employee $actor,
        bool $firstMaterialisation,
        string $channel,
    ): TravelTicketPrintEvent {
        return DB::transaction(function () use ($ticket, $actor, $firstMaterialisation, $channel): TravelTicketPrintEvent {
            TravelTicket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            $previous = TravelTicketPrintEvent::query()
                ->where('ticket_id', $ticket->getKey())
                ->count();

            $event = new TravelTicketPrintEvent([
                'ticket_id' => $ticket->getKey(),
                'employee_id' => $actor->id,
                'channel' => $channel,
                'reprint_number' => $previous,
                'is_reprint' => ! $firstMaterialisation || $previous > 0,
            ]);

            $event->save();

            return $event;
        });
    }
}
