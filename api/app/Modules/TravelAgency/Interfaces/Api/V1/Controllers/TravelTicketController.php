<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CheckInTicketAction;
use App\Modules\TravelAgency\Application\Actions\RecordTicketPrintAction;
use App\Modules\TravelAgency\Application\Actions\VerifyTicketAction;
use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTicketPrintEvent;
use App\Modules\TravelAgency\Infrastructure\Services\TravelTicketPdfGenerator;
use App\Modules\TravelAgency\Infrastructure\Services\TravelTicketPdfStorage;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelTicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-317 (#6047) — Check-in / embarquement.
 * TRAVEL-412/413 (#6064/#6065) — Génération PDF, URL signée, révocation.
 */
class TravelTicketController extends Controller
{
    /**
     * #8538 — VÉRIFICATION d'authenticité d'un billet (lecture seule).
     *
     * Distincte de la consommation (`checkIn`) : vérifier ne rend jamais le
     * billet inutilisable. Accepte le contenu scanné du QR ou une saisie
     * manuelle (référence + code) si la caméra ne fonctionne pas.
     */
    public function verify(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('verifyAny', TravelTicket::class)) {
            abort(403);
        }

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'max:64'],
        ]);

        $result = app(VerifyTicketAction::class)->execute(
            (string) $validated['reference'],
            (string) $validated['code'],
            $actor,
        );

        return response()->json(['data' => $this->verificationPayload($result, $actor)]);
    }

    /**
     * @param  array{booking: TravelBooking, ticket: TravelTicket, status: string}  $result
     * @return array<string, mixed>
     */
    private function verificationPayload(array $result, Employee $actor): array
    {
        $booking = $result['booking'];
        $ticket = $result['ticket'];
        $trip = $booking->trip;
        $route = $trip?->route;
        $passenger = $ticket->passenger;

        return [
            'status' => $result['status'],
            'ticket_number' => $ticket->ticket_number,
            'booking_reference' => $booking->reference,
            'seat_number' => $passenger?->seat_number,
            'trip' => [
                'code' => $trip?->code,
                'departure_date' => $trip?->departure_date?->toDateString(),
                'departure_time' => $trip?->departure_time,
                'origin' => $route?->originCity?->name,
                'destination' => $route?->destinationCity?->name,
            ],
            'checked_in_at' => $ticket->checked_in_at,
            'valid_until' => $ticket->valid_until,
            // Identité du passager : uniquement pour un opérateur habilité au
            // contrôle (permission travel.checkin) — jamais pour une lecture.
            'passenger' => $actor->can('checkIn', $ticket) && $passenger !== null
                ? ['full_name' => $passenger->full_name]
                : null,
        ];
    }

    /**
     * #8546 — RECHERCHE MANUELLE par référence (secours si la caméra ne
     * fonctionne pas, ou appareil sans capteur).
     *
     * LECTURE SEULE, comme `verify` : ne consomme jamais le billet. Ne
     * retourne AUCUN code de contrôle (il n'est délivré qu'à l'émission) —
     * seulement de quoi identifier la réservation et ses billets, pour que
     * l'agent puisse ensuite saisir le code ou demander une pièce d'identité.
     *
     * Cross-tenant → 404 (jamais 403 : ne rien divulguer sur l'existence
     * d'une réservation dans un autre tenant).
     */
    public function lookup(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('verifyAny', TravelTicket::class)) {
            abort(403);
        }

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
        ]);

        $booking = TravelBooking::query()
            ->where('reference', (string) $validated['reference'])
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

        $verifier = app(VerifyTicketAction::class);

        return response()->json([
            'data' => [
                'booking_reference' => $booking->reference,
                'trip' => [
                    'code' => $booking->trip?->code,
                    'departure_date' => $booking->trip?->departure_date?->toDateString(),
                    'departure_time' => $booking->trip?->departure_time,
                    'origin' => $booking->trip?->route?->originCity?->name,
                    'destination' => $booking->trip?->route?->destinationCity?->name,
                ],
                'tickets' => $booking->tickets->map(fn (TravelTicket $ticket): array => [
                    'ticket_number' => $ticket->ticket_number,
                    'seat_number' => $ticket->passenger?->seat_number,
                    'status' => $verifier->resolveStatus($ticket),
                    // Identité du passager : uniquement pour un opérateur
                    // habilité au contrôle (permission travel.checkin).
                    'passenger' => $actor->can('checkIn', $ticket) && $ticket->passenger !== null
                        ? ['full_name' => $ticket->passenger->full_name]
                        : null,
                ])->all(),
            ],
        ]);
    }

    public function checkIn(Request $request, TravelTicket $travelTicket): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTicket->company_id) {
            abort(404);
        }

        if ($actor->cannot('checkIn', $travelTicket)) {
            abort(403);
        }

        $ticket = app(CheckInTicketAction::class)->execute($travelTicket, $actor);

        return (new TravelTicketResource($ticket))->response();
    }

    /**
     * Génère (si absent) et renvoie l'URL signée temporaire du PDF.
     */
    public function pdf(Request $request, TravelTicket $travelTicket): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTicket->company_id) {
            abort(404);
        }

        if ($travelTicket->status === TicketStatus::VOID) {
            abort(410, 'Ce billet a été révoqué.');
        }

        $storage = app(TravelTicketPdfStorage::class);
        $firstMaterialisation = $travelTicket->pdf_asset_id === null;

        if ($firstMaterialisation) {
            $pdf = app(TravelTicketPdfGenerator::class)->generate($travelTicket);
            $path = $storage->store($travelTicket, $pdf);
            $travelTicket->forceFill(['pdf_asset_id' => crc32($path)])->save();
        }

        $path = TravelTicketPdfStorage::PREFIX.'/'.$travelTicket->company_id.'/'.$travelTicket->ticket_number.'.pdf';

        // #8544 — chaque mise à disposition est TRACÉE (opérateur, canal, numéro
        // de réimpression). Réimprimer n'est ni un check-in ni une consommation :
        // le statut du billet n'est jamais modifié ici.
        $event = app(RecordTicketPrintAction::class)->execute($travelTicket, $actor, $firstMaterialisation);

        return response()->json([
            'data' => [
                'ticket_number' => $travelTicket->ticket_number,
                'pdf_url' => $storage->signedUrl($path),
                'expires_in_minutes' => 30,
                'reprint_number' => $event->reprint_number,
                'is_reprint' => $event->is_reprint,
            ],
        ]);
    }

    /**
     * #8544 — HISTORIQUE des mises à disposition du billet.
     *
     * Répond à « réimprimer un billet en conservant une trace de l'opération » :
     * qui a réimprimé, quand, combien de fois. Borné au tenant ; un billet d'un
     * autre tenant répond 404 (jamais 403, pour ne rien divulguer).
     */
    public function printHistory(Request $request, TravelTicket $travelTicket): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTicket->company_id) {
            abort(404);
        }

        if ($actor->cannot('viewPrintHistory', $travelTicket)) {
            abort(403);
        }

        $events = TravelTicketPrintEvent::query()
            ->where('ticket_id', $travelTicket->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'ticket_number' => $travelTicket->ticket_number,
                'reprints_count' => $events->where('is_reprint', true)->count(),
                'events' => $events->map(fn (TravelTicketPrintEvent $event): array => [
                    'reprint_number' => $event->reprint_number,
                    'is_reprint' => $event->is_reprint,
                    'channel' => $event->channel,
                    'employee_id' => $event->employee_id,
                    'occurred_at' => $event->created_at?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    /**
     * Révocation : passage en `void` + suppression du PDF stocké.
     */
    public function revoke(Request $request, TravelTicket $travelTicket): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelTicket->company_id) {
            abort(404);
        }

        if ($actor->cannot('checkIn', $travelTicket)) {
            abort(403);
        }

        if ($travelTicket->status === TicketStatus::CHECKED_IN) {
            abort(422, 'Un billet déjà enregistré ne peut pas être révoqué.');
        }

        $path = TravelTicketPdfStorage::PREFIX.'/'.$travelTicket->company_id.'/'.$travelTicket->ticket_number.'.pdf';
        app(TravelTicketPdfStorage::class)->delete($path);

        $travelTicket->forceFill(['status' => TicketStatus::VOID])->save();

        return (new TravelTicketResource($travelTicket->refresh()))->response();
    }
}
