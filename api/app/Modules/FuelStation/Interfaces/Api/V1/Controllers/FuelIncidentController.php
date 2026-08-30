<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Enums\FuelIncidentStatus;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Infrastructure\Services\FuelIncidentService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\AssignFuelIncidentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelIncidentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\TransitionFuelIncidentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #5804/#5805 — Incidents équipements FuelStation (FUEL-010/FUEL-011).
 *
 * Workflow audité (FuelIncidentService) : signalement par tout employé du
 * tenant, assignation et transitions par manager (ou assigné). Politiques
 * deny-by-default, isolation tenant fail-closed.
 */
class FuelIncidentController extends Controller
{
    use FuelIndexQueryTrait;

    public function __construct(private readonly FuelIncidentService $incidents)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelIncident::class);

        $query = FuelIncident::query()
            ->with(['reporter:id,first_name,last_name', 'assignee:id,first_name,last_name'])
            ->where('company_id', $actor->company_id);

        // Un opérateur non-manager ne voit que ses incidents (assignés ou signalés).
        if (! $actor->isManager()) {
            $query->where(fn ($q) => $q->where('assigned_to', $actor->id)->orWhere('reported_by', $actor->id));
        }

        $incidents = $this->applyFuelIndexQuery(
            $query,
            $request,
            ['title', 'severity', 'status', 'created_at'],
            ['status', 'severity', 'station_id', 'assigned_to'],
        );

        return response()->json(['data' => $incidents->through(fn (FuelIncident $i): array => $this->payload($i))]);
    }

    public function store(StoreFuelIncidentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelIncident::class);

        /** @var FuelIncident $incident */
        $incident = FuelIncident::query()->create(
            $request->validated() + [
                'company_id' => $actor->company_id,
                'reported_by' => $actor->id,
                'status' => FuelIncidentStatus::Open->value,
            ],
        );

        return response()->json(['data' => $this->payload($incident->fresh(['reporter:id,first_name,last_name']))], 201);
    }

    public function show(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($incident, $request->user());
        $this->authorize('view', $incident);

        return response()->json(['data' => $this->payload($incident->loadMissing(['reporter:id,first_name,last_name', 'assignee:id,first_name,last_name']))]);
    }

    public function assign(AssignFuelIncidentRequest $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($incident, $request->user());
        $this->authorize('assign', $incident);

        /** @var Employee $actor */
        $actor = $request->user();

        $assignee = Employee::query()->findOrFail($request->validated()['assigned_to']);

        return response()->json(['data' => $this->payload($this->incidents->assign($incident, $assignee, $actor))]);
    }

    public function transition(TransitionFuelIncidentRequest $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($incident, $request->user());
        $this->authorize('transition', $incident);

        /** @var Employee $actor */
        $actor = $request->user();

        $to = FuelIncidentStatus::tryFrom($request->validated()['status']);

        if ($to === null) {
            return response()->json(['error' => 'fuel_incident_invalid_status'], 422);
        }

        $updated = $this->incidents->transition(
            $incident,
            $to,
            $actor,
            $request->validated()['resolution_note'] ?? null,
        );

        return response()->json(['data' => $this->payload($updated)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'station_id' => $incident->station_id,
            'equipment_type' => $incident->equipment_type,
            'equipment_id' => $incident->equipment_id,
            'title' => $incident->title,
            'description' => $incident->description,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'assigned_to' => $incident->assigned_to,
            'assignee' => $incident->relationLoaded('assignee') && $incident->assignee !== null
                ? ['id' => $incident->assignee->id, 'first_name' => $incident->assignee->first_name, 'last_name' => $incident->assignee->last_name]
                : null,
            'reported_by' => $incident->reported_by,
            'resolved_by' => $incident->resolved_by,
            'resolved_at' => optional($incident->resolved_at)->toIso8601String(),
            'resolution_note' => $incident->resolution_note,
            'created_at' => optional($incident->created_at)->toIso8601String(),
        ];
    }

    private function guardTenant(FuelIncident $incident, mixed $actor): void
    {
        if ($actor instanceof Employee && (string) $incident->company_id !== (string) $actor->company_id) {
            abort(404);
        }
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
