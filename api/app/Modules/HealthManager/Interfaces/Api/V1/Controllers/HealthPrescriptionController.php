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

/**
 * API des ordonnances — HC-005 (#7789, BC-30).
 *
 * CONTENU MÉDICAL (réception → 403). Une ordonnance est émise sur une
 * consultation du tenant, par le praticien AUTEUR de la consultation (ou
 * la direction), avec AU MOINS UNE ligne de médicament — le tout dans une
 * transaction. Historique consultable PAR PATIENT (`?patient_id=`).
 * Immuable après émission : ni update ni delete via l'API.
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

        // Historique des prescriptions PAR PATIENT (critère HC-005).
        if ($request->filled('patient_id')) {
            $query->where('patient_id', (int) $request->input('patient_id'));
        }
        if ($request->filled('consultation_id')) {
            $query->where('consultation_id', (int) $request->input('consultation_id'));
        }

        $prescriptions = $query->orderByDesc('prescribed_at')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($prescriptions->items())->map(fn (HealthPrescription $prescription): array => $this->payload($prescription)),
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

        /** @var HealthConsultation $consultation */
        $consultation = HealthConsultation::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail((int) $validated['consultation_id']);

        // Seul le praticien AUTEUR de la consultation — ou la direction —
        // prescrit sur cette consultation (miroir de la règle d'écriture).
        if (! HealthAccess::isAdmin($actor)
            && HealthAccess::practitionerId($actor) !== $consultation->practitioner_id) {
            abort(403);
        }

        /** @var HealthPrescription $prescription */
        $prescription = DB::transaction(function () use ($validated, $consultation): HealthPrescription {
            /** @var HealthPrescription $created */
            $created = new HealthPrescription([
                'prescribed_at' => $validated['prescribed_at'] ?? now(),
                'notes' => $validated['notes'] ?? null,
            ]);
            // Liens dérivés de la consultation, JAMAIS de la requête.
            $created->consultation_id = (int) $consultation->getAttribute('id');
            $created->patient_id = $consultation->patient_id;
            $created->practitioner_id = $consultation->practitioner_id;
            $created->save();

            /** @var array<int, array<string, string|null>> $items */
            $items = $validated['items'];
            foreach ($items as $item) {
                /** @var HealthPrescriptionItem $line */
                $line = new HealthPrescriptionItem([
                    'medication' => (string) $item['medication'],
                    'dosage' => (string) $item['dosage'],
                    'frequency' => (string) $item['frequency'],
                    'duration' => (string) $item['duration'],
                    'instructions' => $item['instructions'] ?? null,
                ]);
                $line->prescription_id = (int) $created->getAttribute('id');
                $line->save();
            }

            return $created;
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
            'notes' => $prescription->notes,
            'items' => $prescription->items->map(fn (HealthPrescriptionItem $item): array => [
                'id' => (int) $item->getAttribute('id'),
                'medication' => $item->medication,
                'dosage' => $item->dosage,
                'frequency' => $item->frequency,
                'duration' => $item->duration,
                'instructions' => $item->instructions,
            ])->all(),
        ];
    }
}
