<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCashSession;
use App\Modules\TravelAgency\Infrastructure\Services\TravelPdvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-810 (#6100) — Point de vente tablette (caisse + reçus).
 *
 * Session de caisse (ouverture/clôture avec écart serveur) et reçu
 * d'encaissement d'une réservation. Accessible aux agents du tenant
 * (travel.agent) ; la clôture est tracée avec l'opérateur.
 */
class TravelPdvController extends Controller
{
    public function open(Request $request, TravelPdvService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $data = $request->validate([
            'opening_balance_minor' => ['sometimes', 'integer', 'min:0'],
        ]);

        $session = $service->open($actor->company_id, $actor, (int) ($data['opening_balance_minor'] ?? 0));

        return response()->json(['data' => $this->payload($session)])->setStatusCode(201);
    }

    public function current(Request $request, TravelPdvService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $session = $service->current($actor->company_id);

        return response()->json(['data' => $session instanceof TravelCashSession ? $this->payload($session) : null]);
    }

    public function close(Request $request, TravelPdvService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $data = $request->validate([
            'actual_balance_minor' => ['required', 'integer', 'min:0'],
        ]);

        $session = $service->close($actor->company_id, $actor, (int) $data['actual_balance_minor']);

        return response()->json(['data' => $this->payload($session)]);
    }

    public function receipt(Request $request, TravelBooking $booking, TravelPdvService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json(['data' => $service->receipt($actor->company_id, $booking)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TravelCashSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'opened_at' => $session->opened_at->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'opening_balance_minor' => $session->opening_balance_minor,
            'expected_balance_minor' => $session->expected_balance_minor,
            'actual_balance_minor' => $session->actual_balance_minor,
            'difference_minor' => $session->difference_minor,
        ];
    }
}
