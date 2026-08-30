<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelIncidentService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\AssignFuelIncidentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\ResolveFuelIncidentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelIncidentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelIncidentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des incidents FuelStation (FUEL-010, #5804).
 *
 * Signalement ouvert à tout employé authentifié du tenant (report terrain) ;
 * gestion (liste, transitions) réservée au manager. Cycle audité :
 * open → assigned → in_progress → resolved → closed (transitions 422 si
 * illégales). 404 sûr cross-tenant avant Policy.
 */
class FuelIncidentController extends Controller
{
    public function __construct(private readonly FuelIncidentService $incidents) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelIncident::class);

        $query = FuelIncident::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', (int) $request->input('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', (string) $request->input('severity'));
        }

        $incidents = $query->orderByDesc('occurred_at')->paginate((int) min($request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $incidents->map(fn (FuelIncident $incident): array => $this->payload($incident)),
            'meta' => [
                'current_page' => $incidents->currentPage(),
                'last_page' => $incidents->lastPage(),
                'total' => $incidents->total(),
            ],
        ]);
    }

    public function store(StoreFuelIncidentRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('create', FuelIncident::class);

        $incident = $this->incidents->create($actor, $station, $request->validated());

        return response()->json(['data' => $this->payload($incident)], 201);
    }

    public function show(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $incident);

        return response()->json(['data' => $this->payload($incident)]);
    }

    public function update(UpdateFuelIncidentRequest $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $incident);

        $incident->update($request->validated());

        return response()->json(['data' => $this->payload($incident->refresh())]);
    }

    public function assign(AssignFuelIncidentRequest $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('manage', $incident);

        $incident = $this->incidents->assign($actor, $incident, $request->integer('assigned_to', 0) ?: null);

        return response()->json(['data' => $this->payload($incident)]);
    }

    public function start(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('manage', $incident);

        return response()->json(['data' => $this->payload($this->incidents->start($actor, $incident))]);
    }

    public function resolve(ResolveFuelIncidentRequest $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('manage', $incident);

        return response()->json([
            'data' => $this->payload($this->incidents->resolve($actor, $incident, $request->validated())),
        ]);
    }

    public function close(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('manage', $incident);

        return response()->json(['data' => $this->payload($this->incidents->close($actor, $incident))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'company_id' => $incident->company_id,
            'station_id' => $incident->station_id,
            'equipment_type' => $incident->equipment_type,
            'equipment_id' => $incident->equipment_id,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'title' => $incident->title,
            'description' => $incident->description,
            'occurred_at' => $incident->occurred_at?->toISOString(),
            'reported_by' => $incident->reported_by,
            'assigned_to' => $incident->assigned_to,
            'resolved_by' => $incident->resolved_by,
            'resolution_notes' => $incident->resolution_notes,
            'resolved_at' => $incident->resolved_at?->toISOString(),
            'closed_at' => $incident->closed_at?->toISOString(),
            'created_at' => $incident->created_at?->toISOString(),
            'updated_at' => $incident->updated_at?->toISOString(),
        ];
    }
}
