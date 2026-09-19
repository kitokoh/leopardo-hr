<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailPosService;
use App\Modules\Retail\Domain\Models\RetailLocation;
use App\Modules\Retail\Domain\Models\RetailPosSession;
use App\Modules\Retail\Interfaces\Api\V1\Requests\CloseRetailPosSessionRequest;
use App\Modules\Retail\Interfaces\Api\V1\Requests\StoreRetailPosSessionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sessions de caisse POS du module Retail (BC-17 RETAIL, #7674).
 *
 * deny-by-default (RetailPosSessionPolicy) : lecture membres du tenant,
 * ouverture/cloture reservees principal/rh. Toute la logique metier
 * (unicite de session ouverte, attendu de cloture, ecart) est portee par
 * RetailPosService (transactions). Isolation : toute ressource d'un autre
 * tenant repond 404 (lecon fail-closed #3727).
 */
class RetailPosSessionController extends Controller
{
    public function __construct(private readonly RetailPosService $posService) {}

    /**
     * Liste des sessions (filtres status / location_id, ordre desc).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', RetailPosSession::class);

        $query = RetailPosSession::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        $sessions = $query
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($sessions->items())
                ->map(fn (RetailPosSession $s): array => $this->payload($s)),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * Ouvre une session de caisse (une seule session `open` par emplacement).
     */
    public function store(StoreRetailPosSessionRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', RetailPosSession::class);

        /** @var RetailLocation $location */
        $location = RetailLocation::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($request->integer('location_id'));

        $session = $this->posService->openSession(
            location: $location,
            openedByUserId: (int) $actor->id,
            openingCashMinor: $request->integer('opening_cash_minor', 0),
        );

        return response()->json(['data' => $this->payload($session)], 201);
    }

    public function show(Request $request, RetailPosSession $session): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($session->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $session);

        return response()->json(['data' => $this->payload($session)]);
    }

    /**
     * Cloture la session : attendu = fonds d'ouverture + paiements cash
     * captures (calcul serveur), ecart signe persiste avec motif optionnel.
     */
    public function close(CloseRetailPosSessionRequest $request, RetailPosSession $session): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($session->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('close', $session);

        $varianceReason = $request->filled('variance_reason')
            ? (string) $request->input('variance_reason')
            : null;

        $closed = $this->posService->closeSession(
            session: $session,
            countedCashMinor: $request->integer('counted_cash_minor'),
            varianceReason: $varianceReason,
            closedByUserId: (int) $actor->id,
        );

        return response()->json(['data' => $this->payload($closed)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RetailPosSession $session): array
    {
        return [
            'id' => $session->id,
            'company_id' => $session->company_id,
            'location_id' => $session->location_id,
            'opened_at' => $session->opened_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'opened_by_user_id' => $session->opened_by_user_id,
            'closed_by_user_id' => $session->closed_by_user_id,
            'opening_cash_minor' => $session->opening_cash_minor,
            'expected_cash_minor' => $session->expected_cash_minor,
            'counted_cash_minor' => $session->counted_cash_minor,
            'variance_minor' => $session->variance_minor,
            'variance_reason' => $session->variance_reason,
            'status' => $session->status->value,
            'version' => $session->version,
            'created_at' => $session->created_at?->toIso8601String(),
            'updated_at' => $session->updated_at?->toIso8601String(),
        ];
    }
}
