<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HospitalityManager\Domain\Models\HospitalityLease;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityRentService;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\StoreHospitalityLeaseRequest;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\BoundsPagination;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\ChecksHospitalitySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des baux locatifs — HOSP-005 (#7947, BC-32).
 *
 * Index/création imbriqués sous l'établissement ; lecture, mise à jour,
 * résiliation et génération d'échéances en route « shallow »
 * (`/hospitality/leases/{lease}`) — tenant TOUJOURS re-vérifié
 * (404 fail-closed).
 *
 * Aucune suppression : un bail se RÉSILIE (`terminate`), il ne s'efface pas —
 * l'historique locatif et les échéances émises doivent rester lisibles.
 */
class HospitalityLeaseController extends Controller
{
    use BoundsPagination;
    use ChecksHospitalitySolution;

    public function __construct(private readonly HospitalityRentService $rents) {}

    public function index(Request $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->authorize('view', $property);

        $query = HospitalityLease::query()
            ->where('company_id', $actor->company_id)
            ->where('property_id', $property->getKey());

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $leases = $query->orderByDesc('starts_on')->paginate($this->boundedPerPage($request, 15));

        return response()->json([
            'data' => collect($leases->items())->map(fn (HospitalityLease $lease): array => $this->payload($lease)),
            'meta' => [
                'current_page' => $leases->currentPage(),
                'per_page' => $leases->perPage(),
                'total' => $leases->total(),
            ],
        ]);
    }

    public function store(StoreHospitalityLeaseRequest $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->authorize('create', [HospitalityLease::class, $property->getKey()]);

        /** @var HospitalityLease $lease */
        $lease = HospitalityLease::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'property_id' => $property->getKey(),
            'status' => HospitalityLease::STATUS_ACTIVE,
            'billing_day' => (int) $request->input('billing_day', 1),
            'version' => 0,
        ]));

        return response()->json(['data' => $this->payload($lease->refresh())], 201);
    }

    public function show(Request $request, HospitalityLease $lease): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($lease, $actor->company_id);
        $this->authorize('view', $lease);

        return response()->json(['data' => $this->payload($lease)]);
    }

    public function update(StoreHospitalityLeaseRequest $request, HospitalityLease $lease): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($lease, $actor->company_id);
        $this->authorize('update', $lease);

        // Un bail terminal est un document historique : on ne réécrit pas ses
        // conditions (loyer, dates) après coup, sous peine de rendre les
        // échéances déjà émises incohérentes avec le contrat.
        if ($lease->isTerminal()) {
            abort(422, (string) __('hospitality.lease_terminal_immutable'));
        }

        $lease->update(array_merge($request->validated(), [
            'version' => $lease->version + 1,
        ]));

        return response()->json(['data' => $this->payload($lease->refresh())]);
    }

    /**
     * Résiliation (`terminated`) ou fin de bail arrivée à terme (`ended`).
     */
    public function terminate(Request $request, HospitalityLease $lease): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($lease, $actor->company_id);
        $this->authorize('terminate', $lease);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:ended,terminated'],
            'terminated_on' => ['nullable', 'date'],
        ]);

        $lease = $this->rents->terminate(
            $lease,
            (string) ($validated['status'] ?? HospitalityLease::STATUS_TERMINATED),
            $validated['terminated_on'] ?? null,
        );

        return response()->json(['data' => $this->payload($lease)]);
    }

    /**
     * Génère les échéances jusqu'à la période demandée (incluse). Idempotent.
     */
    public function generatePeriods(Request $request, HospitalityLease $lease): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($lease, $actor->company_id);
        $this->authorize('update', $lease);

        $validated = $request->validate([
            'until' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $result = $this->rents->generatePeriods($lease, (string) $validated['until']);

        return response()->json([
            'data' => [
                'lease_id' => (int) $lease->getKey(),
                'until' => $validated['until'],
                'created' => $result['created'],
                'skipped' => $result['skipped'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HospitalityLease $lease): array
    {
        return [
            'id' => (int) $lease->getAttribute('id'),
            'property_id' => (int) $lease->getAttribute('property_id'),
            'unit_id' => (int) $lease->getAttribute('unit_id'),
            'tenant_name' => $lease->tenant_name,
            'contact_email' => $lease->contact_email,
            'contact_phone' => $lease->contact_phone,
            'starts_on' => $lease->starts_on->toDateString(),
            'ends_on' => $lease->ends_on?->toDateString(),
            'monthly_rent_minor' => $lease->monthly_rent_minor,
            'currency' => $lease->currency,
            'billing_day' => $lease->billing_day,
            'deposit_minor' => $lease->deposit_minor,
            'status' => $lease->status,
            'terminated_on' => $lease->terminated_on?->toDateString(),
            'notes' => $lease->notes,
            'version' => $lease->version,
        ];
    }
}
