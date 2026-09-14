<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

/**
 * TRAVEL-412 (#6064) — Générateur de billet PDF (local, QR).
 *
 * Template Blade versionné (`resources/views/travel/ticket.blade.php`),
 * génération 100 % locale via laravel-dompdf (suppression de la dépendance
 * historique ConvertAPI/PHPWord, spec §D6).
 *
 * #7394 — le PDF imprimait le NUMÉRO DE BILLET sous l'étiquette « Code de
 * contrôle », alors que le portail passager réclame le « Code de validation »
 * figurant « sur votre e-billet ». Le passager ne pouvait donc PAS suivre sa
 * réservation : le seul code imprimé n'était pas celui attendu par l'API.
 * Le PDF reçoit désormais le vrai code (déchiffré depuis la copie chiffrée du
 * billet) et l'affiche sous le libellé correct, à côté du numéro de billet.
 */
final class TravelTicketPdfGenerator
{
    public const TEMPLATE = 'travel.ticket';

    /**
     * @return string Contenu binaire du PDF
     */
    public function generate(TravelTicket $ticket): string
    {
        $ticket->load('passenger', 'booking.trip.route');

        $view = View::make(self::TEMPLATE, [
            'ticket' => $ticket,
            'passenger' => $ticket->passenger,
            'booking' => $ticket->booking,
            'trip' => $ticket->booking?->trip,
            'route' => $ticket->booking?->trip?->route,
            // `null` pour les billets émis avant #7394 : le template le signale
            // explicitement plutôt que d'imprimer un faux code.
            'validationCode' => $ticket->plainValidationCode(),
        ]);

        return Pdf::loadHTML($view->render())
            ->setPaper('a4', 'portrait')
            ->output();
    }
}
