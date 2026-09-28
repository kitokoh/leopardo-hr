<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Application\Actions\CreateHealthPrescriptionAction;
use App\Modules\HealthManager\Domain\Models\HealthPrescription;
use App\Modules\HealthManager\Domain\Models\HealthPrescriptionItem;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthPrescriptionRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des ordonnances — HC-005 (#7789, BC-31).
 *
 * Contenu MÉDICAL : praticiens + direction UNIQUEMENT (réception et
 * facturation TOUJOURS refusées, Policy §2). L'ordonnance est liée à une
 * consultation du tenant ; patient et praticien sont DÉRIVÉS de la
 * consultation (jamais fournis par le client) ; ≥ 1 ligne requise ;
 * création ordonnance + lignes en transaction (atomique).
 */
class HealthPrescriptionController extends Controller
{
    use ChecksHealthSolution;

    public function __construct(private readonly CreateHealthPrescriptionAction $createAction) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthPrescription::class);

        $query = HealthPrescription::query()
            ->with('items')
            ->where('company_id', $actor->company_id);

        if ($request->filled('patient_id')) {
            $query->where('patient_id', (int) $request->input('patient_id'));
        }

        $prescriptions = $query->orderByDesc('prescribed_at')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($prescriptions->items())
                ->map(fn (HealthPrescription $prescription): array => $this->payload($prescription)),
            'meta' => [
                'current_page' => $prescriptions->currentPage(),
                'per_page' => $prescriptions->perPage(),
                'total' => $prescriptions->total(),
            ],
        ]);
    }

    public function store(StoreHealthPrescriptionRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthPrescription::class);

        $prescription = $this->createAction->execute($actor, $request->validated());

        return response()->json(['data' => $this->payload($prescription->load('items'))], 201);
    }

    public function show(Request $request, HealthPrescription $prescription): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($prescription, $actor->company_id);
        $this->authorize('view', $prescription);

        return response()->json(['data' => $this->payload($prescription->load('items'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthPrescription $prescription): array
    {
        return [
            'id' => (int) $prescription->getAttribute('id'),
            'consultation_id' => $prescription->consultation_id,
            'patient_id' => $prescription->patient_id,
            'practitioner_id' => $prescription->practitioner_id,
            'prescribed_at' => $prescription->prescribed_at->toISOString(),
            'notes' => $prescription->notes_encrypted,
            'items' => $prescription->items->map(fn (HealthPrescriptionItem $item): array => [
                'id' => (int) $item->getAttribute('id'),
                'medication' => $item->medication,
                'dosage' => $item->dosage,
                'frequency' => $item->frequency,
                'duration' => $item->duration,
                'instructions' => $item->instructions,
            ])->values()->all(),
        ];
    }
}
