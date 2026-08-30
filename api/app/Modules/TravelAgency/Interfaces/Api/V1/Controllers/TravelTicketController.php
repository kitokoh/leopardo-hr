<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CheckInTicketAction;
use App\Modules\TravelAgency\Domain\Enums\TicketStatus;
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
