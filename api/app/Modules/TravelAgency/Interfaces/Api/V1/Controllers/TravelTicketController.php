<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\CheckInTicketAction;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelTicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-317 (#6047) — Check-in / embarquement d'un billet.
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
}
