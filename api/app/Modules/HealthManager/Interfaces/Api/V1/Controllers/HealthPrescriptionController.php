<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthConsultation;
use App\Modules\HealthManager\Domain\Models\HealthPrescription;
use App\Modules\HealthManager\Domain\Models\HealthPrescriptionItem;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthPrescriptionRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        $validated = $request->validated();

        // Consultation du MÊME tenant (cross-tenant → 404 fail-closed).
        /** @var HealthConsultation $consultation */
        $consultation = HealthConsultation::query()
            ->where('company_id', $actor->company_id)
            ->whereKey((int) $validated['consultation_id'])
            ->firstOrFail();

        // Un praticien (non direction) ne prescrit que sur SES consultations.
        if (! HealthAccess::isAdmin($actor)
            && $consultation->practitioner_id !== HealthAccess::practitionerId($actor)
        ) {
            throw ValidationException::withMessages([
                'consultation_id' => ['Seul le praticien de la consultation peut prescrire.'],
            ]);
        }

        /** @var HealthPrescription $prescription */
        $prescription = DB::transaction(function () use ($actor, $consultation, $validated): HealthPrescription {
            /** @var HealthPrescription $prescription */
            $prescription = HealthPrescription::query()->create([
                'company_id' => $actor->company_id,
                'consultation_id' => (int) $consultation->getAttribute('id'),
                // Dérivés de la consultation — jamais fournis par le client.
                'patient_id' => $consultation->patient_id,
                'practitioner_id' => $consultation->practitioner_id,
                'prescribed_at' => $validated['prescribed_at'] ?? now(),
                'notes_encrypted' => $validated['notes'] ?? null,
            ]);

            /** @var array<int, array<string, mixed>> $items */
            $items = $validated['items'];

            foreach ($items as $item) {
                HealthPrescriptionItem::query()->create([
                    'company_id' => $actor->company_id,
                    'prescription_id' => (int) $prescription->getAttribute('id'),
                    'medication' => $item['medication'],
                    'dosage' => $item['dosage'] ?? null,
                    'frequency' => $item['frequency'] ?? null,
                    'duration' => $item['duration'] ?? null,
                    'instructions' => $item['instructions'] ?? null,
                ]);
            }

            return $prescription;
        });

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
