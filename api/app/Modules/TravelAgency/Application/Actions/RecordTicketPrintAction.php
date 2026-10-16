<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTicketPrintEvent;
use Illuminate\Support\Facades\DB;

/**
 * #8544 (TRAVEL-OS) — trace une mise à disposition du PDF d'un billet.
 *
 * Sémantique du compteur :
 * - `reprint_number = 0` → PREMIÈRE mise à disposition (émission du PDF) ;
 * - `reprint_number = n > 0` → n-ième réimpression.
 *
 * `is_reprint` reste honnête dans un cas limite : si le PDF avait déjà été
 * matérialisé par un flux antérieur à #8544 (aucun événement enregistré), la
 * mise à disposition n'est PAS une première émission, même si le compteur
 * d'événements vaut 0.
 *
 * Aucune écriture sur `travel_tickets` : ce journal ne touche ni au statut, ni
 * à `checked_in_at`. Réimprimer n'embarque personne.
 */
final class RecordTicketPrintAction
{
    public const CHANNEL_API = 'api';

    public const CHANNEL_OFFLINE_SYNC = 'offline_sync';

    public function execute(
        TravelTicket $ticket,
        Employee $actor,
        bool $firstMaterialisation,
        string $channel = self::CHANNEL_API,
    ): TravelTicketPrintEvent {
        return DB::transaction(function () use ($ticket, $actor, $firstMaterialisation, $channel): TravelTicketPrintEvent {
            // Sérialise les impressions simultanées du MÊME billet (deux agents
            // au même guichet) : le compteur reste strictement croissant.
            // Un `lockForUpdate()` sur un agrégat est refusé par PostgreSQL —
            // on verrouille donc la ligne du billet, puis on compte.
            TravelTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $previous = TravelTicketPrintEvent::query()
                ->where('ticket_id', $ticket->id)
                ->count();

            $event = new TravelTicketPrintEvent([
                'ticket_id' => $ticket->id,
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
