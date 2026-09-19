<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthConsultation;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthConsultationRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthConsultationRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * API des consultations (dossier médical) — HC-005 (#7789, BC-30).
 *
 * CONTENU MÉDICAL : praticiens actifs et direction lisent ; seul le
 * praticien AUTEUR (ou la direction) modifie SA consultation ; la
 * réception n'accède JAMAIS au contenu médical (403 — critère HC-005).
 * Pas de suppression : un dossier médical ne s'efface pas via l'API.
 * `clinical_exam`, `diagnosis` et `notes` chiffrés au repos.
 */
class HealthConsultationController extends Controller
{
    use ChecksHealthSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthConsultation::class);

        $query = HealthConsultation::query()->where('company_id', $actor->company_id);

        if ($request->filled('patient_id')) {
            $query->where('patient_id', (int) $request->input('patient_id'));
        }
        if ($request->filled('practitioner_id')) {
            $query->where('practitioner_id', (int) $request->input('practitioner_id'));
        }

        $consultations = $query->orderByDesc('consulted_at')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($consultations->items())->map(fn (HealthConsultation $consultation): array => $this->payload($consultation)),
            'meta' => [
                'current_page' => $consultations->currentPage(),
                'per_page' => $consultations->perPage(),
                'total' => $consultations->total(),
            ],
        ]);
    }

    public function store(StoreHealthConsultationRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthConsultation::class);

        $validated = $request->validated();

        // L'AUTEUR est posé côté serveur : un praticien documente SA
        // consultation ; la direction non praticienne désigne le praticien.
        $ownPractitionerId = HealthAccess::practitionerId($actor);
        $practitionerId = $ownPractitionerId
            ?? (isset($validated['practitioner_id']) ? (int) $validated['practitioner_id'] : null);

        if ($practitionerId === null) {
            throw ValidationException::withMessages([
                'practitioner_id' => ['Le praticien auteur de la consultation est requis.'],
            ]);
        }

        unset($validated['practitioner_id']);

        /** @var HealthConsultation $consultation */
        $consultation = new HealthConsultation($validated);
        $consultation->practitioner_id = $practitionerId;
        $consultation->save();

        return response()->json(['data' => $this->payload($consultation)], 201);
    }

    public function show(Request $request, HealthConsultation $consultation): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($consultation, $actor->company_id);
        $this->authorize('view', $consultation);

        return response()->json(['data' => $this->payload($consultation)]);
    }

    public function update(UpdateHealthConsultationRequest $request, HealthConsultation $consultation): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($consultation, $actor->company_id);
        $this->authorize('update', $consultation);

        $consultation->update($request->validated());

        return response()->json(['data' => $this->payload($consultation->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthConsultation $consultation): array
    {
        return [
            'id' => (int) $consultation->getAttribute('id'),
            'patient_id' => $consultation->patient_id,
            'practitioner_id' => $consultation->practitioner_id,
            'appointment_id' => $consultation->appointment_id,
            'consulted_at' => $consultation->consulted_at->toISOString(),
            'reason' => $consultation->reason,
            'clinical_exam' => $consultation->clinical_exam,
            'diagnosis' => $consultation->diagnosis,
            'weight_kg' => $consultation->weight_kg,
            'height_cm' => $consultation->height_cm,
            'blood_pressure' => $consultation->blood_pressure,
            'temperature_c' => $consultation->temperature_c,
            'pulse_bpm' => $consultation->pulse_bpm,
            'notes' => $consultation->notes,
        ];
    }
}
