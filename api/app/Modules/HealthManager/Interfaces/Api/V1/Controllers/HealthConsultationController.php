<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Application\Actions\RecordHealthConsultationAction;
use App\Modules\HealthManager\Application\Actions\UpdateHealthConsultationAction;
use App\Modules\HealthManager\Domain\Models\HealthConsultation;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthConsultationRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthConsultationRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function __construct(
        private readonly RecordHealthConsultationAction $recordAction,
        private readonly UpdateHealthConsultationAction $updateAction,
    ) {}

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

        $consultation = $this->recordAction->execute($actor, $request->validated());

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

        $consultation = $this->updateAction->execute($consultation, $request->validated());

        return response()->json(['data' => $this->payload($consultation)]);
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
