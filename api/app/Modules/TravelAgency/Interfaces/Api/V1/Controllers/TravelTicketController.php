<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CheckInTicketAction;
use App\Modules\TravelAgency\Application\Actions\VerifyTicketAction;
use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
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

        if ($travelTicket->pdf_asset_id === null) {
            $pdf = app(TravelTicketPdfGenerator::class)->generate($travelTicket);
            $path = $storage->store($travelTicket, $pdf);
            $travelTicket->forceFill(['pdf_asset_id' => crc32($path)])->save();
        }

        $path = TravelTicketPdfStorage::PREFIX.'/'.$travelTicket->company_id.'/'.$travelTicket->ticket_number.'.pdf';

        return response()->json([
            'data' => [
                'ticket_number' => $travelTicket->ticket_number,
                'pdf_url' => $storage->signedUrl($path),
                'expires_in_minutes' => 30,
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
