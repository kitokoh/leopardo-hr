<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelCashSessionMovement;
use App\Modules\FuelStation\Infrastructure\Services\FuelCashSessionService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\CloseFuelCashSessionRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\OpenFuelCashSessionRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelCashSessionMovementRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sessions de caisse FuelStation (FUEL-007, issue #5801).
 *
 * Pompiste : ouvrir / mouvements / clôturer SES sessions (policy par
 * opened_by). Manager : lister, voir, approuver. Isolation tenant
 * fail-closed (404 cross-tenant). Clôture idempotente ; écart calculé
 * serveur ; écritures d'audit sur clôture et approbation.
 */
class FuelCashSessionController extends Controller
{
    public function __construct(
        private readonly FuelCashSessionService $sessions,
        private readonly DataAccessAuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelCashSession::class);

        $query = FuelCashSession::query()
            ->withCount('movements')
            ->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->input('station_id'));
        }

        $dateFilter = $request->input('date');
        if (is_string($dateFilter) && $dateFilter !== '') {
            $query->whereDate('opened_at', $dateFilter);
        }

        $sessions = $query->orderByDesc('opened_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($sessions->items())->map(fn (FuelCashSession $session): array => $this->payload($session)),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    public function store(OpenFuelCashSessionRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelCashSession::class);

        $session = $this->sessions->open($actor, $request->validated());

        return response()->json(['data' => $this->payload($session)], 201);
    }

    public function show(Request $request, FuelCashSession $session): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($session->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $session);

        return response()->json(['data' => $this->payload($session->load('movements'))]);
    }

    public function addMovement(StoreFuelCashSessionMovementRequest $request, FuelCashSession $session): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($session->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('addMovement', $session);

        $movement = $this->sessions->addMovement($session, $actor, $request->validated());

        return response()->json(['data' => $this->movementPayload($movement)], 201);
    }

    public function close(CloseFuelCashSessionRequest $request, FuelCashSession $session): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($session->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('close', $session);

        $session = $this->sessions->close($session, $actor, $request->validated());

        $this->audit->record($request, $actor, 'fuel.cash_session.closed', $session, [
            'category' => 'fuel_cash_session',
            'variance' => $session->variance,
            'status' => $session->status,
        ]);

        return response()->json(['data' => $this->payload($session)]);
    }

    public function approve(Request $request, FuelCashSession $session): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($session->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('approve', $session);

        $session = $this->sessions->approve($session, $actor);

        $this->audit->record($request, $actor, 'fuel.cash_session.approved', $session, [
            'category' => 'fuel_cash_session',
            'variance' => $session->variance,
            'status' => $session->status,
        ]);

        return response()->json(['data' => $this->payload($session)]);
    }

    /**
     * Self-service pompiste : ses propres sessions.
     */
    public function mySessions(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $query = FuelCashSession::query()
            ->withCount('movements')
            ->where('company_id', $actor->company_id)
            ->where('opened_by', $actor->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sessions = $query->orderByDesc('opened_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($sessions->items())->map(fn (FuelCashSession $session): array => $this->payload($session)),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelCashSession $session): array
    {
        return [
            'id' => $session->id,
            'company_id' => $session->company_id,
            'station_id' => $session->station_id,
            'opened_by' => $session->opened_by,
            'opened_at' => $session->opened_at->toISOString(),
            'closed_by' => $session->closed_by,
            'closed_at' => $session->closed_at?->toISOString(),
            'opening_balance' => $session->opening_balance,
            'closing_balance' => $session->closing_balance,
            'expected_balance' => $session->expected_balance,
            'variance' => $session->variance,
            'status' => $session->status,
            'approved_by' => $session->approved_by,
            'approved_at' => $session->approved_at?->toISOString(),
            'notes' => $session->notes,
            'movements_count' => $session->movements_count ?? null,
            'movements' => $session->relationLoaded('movements')
                ? $session->movements->map(fn (FuelCashSessionMovement $movement): array => $this->movementPayload($movement))->values()
                : null,
            'created_at' => $session->created_at->toISOString(),
            'updated_at' => $session->updated_at->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function movementPayload(FuelCashSessionMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'session_id' => $movement->session_id,
            'type' => $movement->type,
            'amount' => $movement->amount,
            'reason' => $movement->reason,
            'created_by' => $movement->created_by,
            'created_at' => $movement->created_at->toISOString(),
        ];
    }
}
