<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTicketPrintEvent;
use App\Modules\TravelAgency\Infrastructure\Services\TravelTicketPrintRecorder;

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
 *
 * La transaction et le verrou vivent dans
 * {@see TravelTicketPrintRecorder} (Infrastructure) : l'Application ne touche
 * pas aux façades Laravel (garde CI #6568).
 */
final class RecordTicketPrintAction
{
    public const CHANNEL_API = 'api';

    public const CHANNEL_OFFLINE_SYNC = 'offline_sync';

    public function __construct(private readonly TravelTicketPrintRecorder $recorder) {}

    public function execute(
        TravelTicket $ticket,
        Employee $actor,
        bool $firstMaterialisation,
        string $channel = self::CHANNEL_API,
    ): TravelTicketPrintEvent {
        return $this->recorder->record($ticket, $actor, $firstMaterialisation, $channel);
    }
}
