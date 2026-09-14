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
 * #7394 : le billet porte le CODE DE CONTRÔLE réel (code de validation
 * canonique du billet, dérivé côté serveur, jamais persisté en clair) — et
 * non plus le numéro de billet, que l'API refusait. C'est ce même code que le
 * passager saisit sur l'espace voyageur (`?code=`).
 */
final class TravelTicketPdfGenerator
{
    public const TEMPLATE = 'travel.ticket';

    /**
     * @return string Contenu binaire du PDF
     */
    public function generate(TravelTicket $ticket): string
    {
        return Pdf::loadHTML(View::make(self::TEMPLATE, $this->viewData($ticket))->render())
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /**
     * Données du template — exposées pour permettre de vérifier le contenu
     * imprimé sans dépendre du binaire dompdf (compression Flate).
     *
     * @return array<string, mixed>
     */
    public function viewData(TravelTicket $ticket): array
    {
        $ticket->load('passenger', 'booking.trip.route');

        return [
            'ticket' => $ticket,
            'passenger' => $ticket->passenger,
            'booking' => $ticket->booking,
            'trip' => $ticket->booking?->trip,
            'route' => $ticket->booking?->trip?->route,
            // Code de contrôle EN CLAIR (#7394) — imprimé sur le billet et
            // accepté par l'API (secret partagé du billet).
            'validationCode' => $ticket->plainValidationCode(),
        ];
    }
}
