<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Domain\Models\HealthConsultation;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthConsultationRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthConsultationRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * API des consultations médicales — HC-005 (#7789, BC-31).
 *
 * Contenu MÉDICAL (chiffré au repos) : visible des praticiens et de la
 * direction UNIQUEMENT — la réception et la facturation sont TOUJOURS
 * refusées, même en lecture (confidentialité médicale, Policy §2).
 * Un praticien crée SES consultations (practitioner_id forcé à sa fiche) ;
 * la mise à jour est réservée à l'AUTEUR ou à la direction.
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
            'data' => collect($consultations->items())
                ->map(fn (HealthConsultation $consultation): array => $this->payload($consultation)),
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

        // Patient du MÊME tenant (cross-tenant → 404 fail-closed).
        HealthPatient::query()
            ->where('company_id', $actor->company_id)
            ->whereKey((int) $validated['patient_id'])
            ->firstOrFail();

        // Rendez-vous optionnel, même tenant (404 fail-closed).
        if (isset($validated['appointment_id'])) {
            HealthAppointment::query()
                ->where('company_id', $actor->company_id)
                ->whereKey((int) $validated['appointment_id'])
                ->firstOrFail();
        }

        $consultation = HealthConsultation::query()->create(array_merge(
            $this->medicalAttributes($validated),
            [
                'company_id' => $actor->company_id,
                'patient_id' => (int) $validated['patient_id'],
                'practitioner_id' => $this->resolvePractitionerId($actor, $validated),
                'appointment_id' => isset($validated['appointment_id']) ? (int) $validated['appointment_id'] : null,
                'consulted_at' => $validated['consulted_at'],
                'reason' => $validated['reason'] ?? null,
            ]
        ));

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

        $validated = $request->validated();
        $attributes = $this->medicalAttributes($validated);

        foreach (['consulted_at', 'reason'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        $consultation->update($attributes);

        return response()->json(['data' => $this->payload($consultation->refresh())]);
    }

    /**
     * Praticien acteur → SA fiche (forcé, jamais celle d'un tiers) ;
     * la direction peut désigner explicitement un praticien du tenant.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolvePractitionerId(Employee $actor, array $validated): int
    {
        if (HealthAccess::isAdmin($actor) && isset($validated['practitioner_id'])) {
            /** @var HealthPractitioner $practitioner */
            $practitioner = HealthPractitioner::query()
                ->where('company_id', $actor->company_id)
                ->whereKey((int) $validated['practitioner_id'])
                ->firstOrFail();

            return (int) $practitioner->getAttribute('id');
        }

        $ownId = HealthAccess::practitionerId($actor);

        if ($ownId === null) {
            throw ValidationException::withMessages([
                'practitioner_id' => ['Le praticien de la consultation est requis.'],
            ]);
        }

        return $ownId;
    }

    /**
     * Mappe l'API (clinical_exam, diagnosis, vitals, notes) vers les
     * colonnes chiffrées au repos (`*_encrypted`).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function medicalAttributes(array $validated): array
    {
        $attributes = [];

        foreach ([
            'clinical_exam' => 'clinical_exam_encrypted',
            'diagnosis' => 'diagnosis_encrypted',
            'vitals' => 'vitals_encrypted',
            'notes' => 'notes_encrypted',
        ] as $input => $column) {
            if (array_key_exists($input, $validated)) {
                $attributes[$column] = $validated[$input];
            }
        }

        return $attributes;
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
            'clinical_exam' => $consultation->clinical_exam_encrypted,
            'diagnosis' => $consultation->diagnosis_encrypted,
            'vitals' => $consultation->vitals_encrypted,
            'notes' => $consultation->notes_encrypted,
        ];
    }
}
