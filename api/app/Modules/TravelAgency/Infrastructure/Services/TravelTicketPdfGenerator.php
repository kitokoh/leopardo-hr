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
 * historique ConvertAPI/PHPWord, spec §D6). Le QR encode le numéro de
 * billet (le code de validation en clair n'est jamais embarqué dans le
 * PDF non plus — seul le numéro nominatif, vérifiable côté plateforme).
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
        ]);

        return Pdf::loadHTML($view->render())
            ->setPaper('a4', 'portrait')
            ->output();
    }
}
