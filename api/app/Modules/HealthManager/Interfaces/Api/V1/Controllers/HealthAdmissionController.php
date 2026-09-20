<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Models\HealthAdmission;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Infrastructure\Services\HealthAdmissionService;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\DischargeHealthAdmissionRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthAdmissionRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\TransferHealthAdmissionRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API des hospitalisations (admissions & lits) — HC-006 (#7790, BC-31).
 *
 * Direction + réception gèrent les admissions (SANS contenu médical),
 * praticien en lecture (Policy §2). La machine à états des lits (free →
 * occupied, 409 HEALTH_BED_OCCUPIED, transfert atomique, sortie qui
 * libère) est portée par `HealthAdmissionService` en transaction +
 * `lockForUpdate` (spec §4).
 */
class HealthAdmissionController extends Controller
{
    use ChecksHealthSolution;

    public function __construct(private readonly HealthAdmissionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthAdmission::class);

        $request->validate([
            'status' => ['nullable', Rule::in(HealthAdmission::STATUSES)],
        ]);

        $query = HealthAdmission::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $admissions = $query->orderByDesc('admitted_at')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($admissions->items())
                ->map(fn (HealthAdmission $admission): array => $this->payload($admission)),
            'meta' => [
                'current_page' => $admissions->currentPage(),
                'per_page' => $admissions->perPage(),
                'total' => $admissions->total(),
            ],
        ]);
    }

    public function store(StoreHealthAdmissionRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthAdmission::class);

        $validated = $request->validated();

        $companyId = $actor->company_id;
        abort_if($companyId === null, 404);

        // Patient et praticien référent du MÊME tenant (404 fail-closed).
        HealthPatient::query()
            ->where('company_id', $companyId)
            ->whereKey((int) $validated['patient_id'])
            ->firstOrFail();
        HealthPractitioner::query()
            ->where('company_id', $companyId)
            ->whereKey((int) $validated['practitioner_id'])
            ->firstOrFail();

        $admission = $this->service->admit($companyId, $validated);

        return response()->json(['data' => $this->payload($admission)], 201);
    }

    /**
     * Occupation des lits par service (total, occupés, libres, taux %).
     */
    public function occupancy(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthAdmission::class);

        $companyId = $actor->company_id;
        abort_if($companyId === null, 404);

        return response()->json($this->service->occupancy($companyId));
    }

    public function show(Request $request, HealthAdmission $admission): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($admission, $actor->company_id);
        $this->authorize('view', $admission);

        return response()->json(['data' => $this->payload($admission)]);
    }

    public function transfer(TransferHealthAdmissionRequest $request, HealthAdmission $admission): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($admission, $actor->company_id);
        $this->authorize('update', $admission);

        $admission = $this->service->transfer($admission, (int) $request->validated()['bed_id']);

        return response()->json(['data' => $this->payload($admission)]);
    }

    public function discharge(DischargeHealthAdmissionRequest $request, HealthAdmission $admission): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($admission, $actor->company_id);
        $this->authorize('update', $admission);

        /** @var string|null $notes */
        $notes = $request->validated()['discharge_notes'] ?? null;
        $admission = $this->service->discharge($admission, $notes);

        return response()->json(['data' => $this->payload($admission)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthAdmission $admission): array
    {
        return [
            'id' => (int) $admission->getAttribute('id'),
            'patient_id' => $admission->patient_id,
            'practitioner_id' => $admission->practitioner_id,
            'department_id' => $admission->department_id,
            'bed_id' => $admission->bed_id,
            'reason' => $admission->reason,
            'admitted_at' => $admission->admitted_at->toISOString(),
            'expected_discharge_at' => $admission->expected_discharge_at?->toISOString(),
            'discharged_at' => $admission->discharged_at?->toISOString(),
            'status' => $admission->status,
            'discharge_notes' => $admission->discharge_notes,
        ];
    }
}
