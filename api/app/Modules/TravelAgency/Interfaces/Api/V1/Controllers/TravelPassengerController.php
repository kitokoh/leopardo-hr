<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Infrastructure\Services\TravelTicketPdfGenerator;
use App\Modules\TravelAgency\Infrastructure\Services\TravelTicketPdfStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #7395 — « Espace voyageur » : surface PUBLIQUE du client final.
 *
 * Distincte de :
 *  - `TravelShopController` (authentifié staff) — réservé aux employés de
 *    l'agence ;
 *  - `TravelPublicShopController` (jeton boutique `X-Travel-Shop-Token`) —
 *    secret B2B qui résout le tenant pour un embout de boutique ; un passager
 *    ne l'a pas et ne doit pas l'avoir.
 *
 * Le passager s'authentifie avec ce qu'il a : la **référence de réservation**
 * et le **code de validation** imprimés sur son e-billet (#7394). Le tenant est
 * résolu par `EnsureTravelPassengerAccess`, puis le code est vérifié par hash.
 *
 * Charge utile volontairement MINIMALE : aucune PII passager (nom, pièce
 * d'identité), aucune donnée d'un autre passager de la réservation. Le
 * porteur du couple référence + code voit l'état de SA réservation, rien de plus.
 */
class TravelPassengerController extends Controller
{
    /**
     * Suivi d'une réservation par référence + code de validation.
     */
    public function track(Request $request, string $reference): JsonResponse
    {
        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            abort(422, 'Le code de validation est requis.');
        }

        /** @var TravelBooking|null $booking */
        $booking = TravelBooking::query()
            ->where('reference', $reference)
            ->with('trip.route.originCity', 'trip.route.destinationCity', 'tickets')
            ->first();

        if (! $booking instanceof TravelBooking) {
            abort(404);
        }

        // Le code d'AU MOINS un billet de la réservation doit correspondre.
        // Sans cela, la référence seule (imprimée, dicible, devinable) ouvrirait
        // les données : c'est le code qui porte l'authentification.
        $matches = $booking->tickets->contains(
            fn (TravelTicket $ticket): bool => $ticket->validationCodeMatches($code)
        );

        if (! $matches) {
            // Même réponse qu'une référence inconnue : pas d'oracle.
            abort(404);
        }

        $trip = $booking->trip;
        $route = $trip?->route;

        return response()->json([
            'data' => [
                'reference' => $booking->reference,
                'status' => $booking->status->value,
                'payment_status' => $booking->payment_status->value,
                'passenger_count' => $booking->passenger_count,
                'total_amount_minor' => $booking->total_amount_minor,
                'currency' => $booking->currency,
                'trip' => $trip === null ? null : [
                    'code' => $trip->code,
                    'departure_date' => $trip->departure_date?->toDateString(),
                    'departure_time' => $trip->departure_time,
                    'arrival_date' => $trip->arrival_date?->toDateString(),
                    'arrival_time' => $trip->arrival_time,
                    'origin' => $route?->originCity?->name,
                    'destination' => $route?->destinationCity?->name,
                ],
                // Nécessaires au téléchargement de l'e-billet : le numéro est
                // imprimé sur le billet, il n'apporte aucun secret.
                'ticket_numbers' => $booking->tickets
                    ->map(fn (TravelTicket $ticket): string => $ticket->ticket_number)
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * E-billet PDF d'un passager.
     *
     * La route porte la RÉFÉRENCE (seul identifiant « URL-safe ») ; le numéro de
     * billet — qui est imprimé sous la forme `#GV-…`, avec un `#` hostile en
     * segment de chemin (un proxy peut l'interpréter comme un fragment) — passe
     * en paramètre de requête `number`.
     *
     * Vérifications : le billet doit appartenir à CETTE réservation, et le code
     * fourni doit correspondre à son hash. Les deux conditions sont nécessaires.
     */
    public function ticketPdf(Request $request, string $reference): JsonResponse
    {
        $code = trim((string) $request->query('code', ''));
        $ticketNumber = trim((string) $request->query('number', ''));

        if ($code === '' || $ticketNumber === '') {
            abort(422, 'Le numéro de billet et le code de validation sont requis.');
        }

        /** @var TravelBooking|null $booking */
        $booking = TravelBooking::query()
            ->where('reference', $reference)
            ->first();

        if (! $booking instanceof TravelBooking) {
            abort(404);
        }

        /** @var TravelTicket|null $ticket */
        $ticket = TravelTicket::query()
            ->where('booking_id', $booking->id)
            ->where('ticket_number', $ticketNumber)
            ->first();

        // Uniformité : billet inconnu et code invalide donnent la même réponse
        // (403 ici, car la route est explicitement « e-billet » et non
        // « réservation » — cf. TravelPublicShopController::ticketPdf).
        if (! $ticket instanceof TravelTicket) {
            abort(403, 'Code de validation invalide.');
        }

        if (! $ticket->validationCodeMatches($code)) {
            abort(403, 'Code de validation invalide.');
        }

        if ($ticket->status === TicketStatus::VOID) {
            abort(410, 'Ce billet a été révoqué.');
        }

        $storage = app(TravelTicketPdfStorage::class);

        if ($ticket->pdf_asset_id === null) {
            $pdf = app(TravelTicketPdfGenerator::class)->generate($ticket);
            $path = $storage->store($ticket, $pdf);
            $ticket->forceFill(['pdf_asset_id' => crc32($path)])->save();
        }

        $path = TravelTicketPdfStorage::PREFIX.'/'.$ticket->company_id.'/'.$ticket->ticket_number.'.pdf';

        return response()->json([
            'data' => [
                'ticket_number' => $ticket->ticket_number,
                'pdf_url' => $storage->signedUrl($path),
                'expires_in_minutes' => 30,
            ],
        ]);
    }
}
