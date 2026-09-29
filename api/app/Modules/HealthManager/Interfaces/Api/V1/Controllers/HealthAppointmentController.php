<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Application\Actions\ScheduleHealthAppointmentAction;
use App\Modules\HealthManager\Application\Actions\TransitionHealthAppointmentStatusAction;
use App\Modules\HealthManager\Application\Actions\UpdateHealthAppointmentAction;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthAppointmentRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\TransitionHealthAppointmentRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthAppointmentRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * API des rendez-vous & agenda — HC-004 (#7788, BC-31).
 *
 * RBAC (HealthAppointmentPolicy) : direction + réception gèrent TOUS les
 * rendez-vous ; un praticien voit/transitionne UNIQUEMENT les siens et ne
 * peut PAS en créer. Invariants (HealthAppointmentService, spec §4) :
 * chevauchement praticien → 409 HEALTH_APPOINTMENT_CONFLICT ; transitions
 * bornées → 422 HEALTH_INVALID_TRANSITION.
 */
class HealthAppointmentController extends Controller
{
    use ChecksHealthSolution;

    public function __construct(
        private readonly ScheduleHealthAppointmentAction $scheduleAction,
        private readonly UpdateHealthAppointmentAction $updateAction,
        private readonly TransitionHealthAppointmentStatusAction $transitionAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthAppointment::class);

        $query = HealthAppointment::query()->where('company_id', $actor->company_id);

        // Praticien (non gestionnaire) : périmètre restreint à SON agenda,
        // quel que soit le filtre practitioner_id demandé.
        $ownPractitionerId = $this->restrictedPractitionerId($actor);
        if ($ownPractitionerId !== null) {
            $query->where('practitioner_id', $ownPractitionerId);
        } elseif ($request->filled('practitioner_id')) {
            $query->where('practitioner_id', (int) $request->input('practitioner_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('starts_at', (string) $request->input('date'));
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', (int) $request->input('patient_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $appointments = $query->orderBy('starts_at')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($appointments->items())->map(fn (HealthAppointment $appointment): array => $this->payload($appointment)),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ],
        ]);
    }

    public function store(StoreHealthAppointmentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        // Policy : direction + réception uniquement (un praticien ne crée
        // pas ses propres rendez-vous — deny-by-default).
        $this->authorize('create', HealthAppointment::class);

        $appointment = $this->scheduleAction->execute((string) $actor->company_id, $request->validated());

        return response()->json(['data' => $this->payload($appointment)], 201);
    }

    /**
     * Agenda d'un praticien sur une fenêtre [from, to] — GET
     * ?practitioner_id&from&to. Un praticien (non gestionnaire) est
     * TOUJOURS restreint à son propre agenda.
     */
    public function agenda(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthAppointment::class);

        $ownPractitionerId = $this->restrictedPractitionerId($actor);
        $practitionerId = $ownPractitionerId
            ?? ($request->filled('practitioner_id') ? (int) $request->input('practitioner_id') : null);

        $query = HealthAppointment::query()->where('company_id', $actor->company_id);

        if ($practitionerId !== null) {
            $query->where('practitioner_id', $practitionerId);
        }

        if ($request->filled('from')) {
            $query->where('ends_at', '>=', Carbon::parse((string) $request->input('from')));
        }

        if ($request->filled('to')) {
            $query->where('starts_at', '<=', Carbon::parse((string) $request->input('to')));
        }

        $appointments = $query->orderBy('starts_at')->get();

        return response()->json([
            'data' => $appointments->map(fn (HealthAppointment $appointment): array => $this->payload($appointment)),
            'meta' => [
                'practitioner_id' => $practitionerId,
                'total' => $appointments->count(),
            ],
        ]);
    }

    public function show(Request $request, HealthAppointment $appointment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($appointment, $actor->company_id);
        $this->authorize('view', $appointment);

        return response()->json(['data' => $this->payload($appointment)]);
    }

    public function update(UpdateHealthAppointmentRequest $request, HealthAppointment $appointment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($appointment, $actor->company_id);
        $this->authorize('update', $appointment);

        $appointment = $this->updateAction->execute($appointment, $request->validated());

        return response()->json(['data' => $this->payload($appointment)]);
    }

    /**
     * Transition de statut (POST /status) — machine à états spec §4.
     */
    public function transition(TransitionHealthAppointmentRequest $request, HealthAppointment $appointment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($appointment, $actor->company_id);
        // Policy update : gestionnaires, ou praticien sur SES rendez-vous
        // (il peut p. ex. passer les siens à `completed`).
        $this->authorize('update', $appointment);

        /** @var string $target */
        $target = $request->validated()['status'];

        $appointment = $this->transitionAction->execute($appointment, $target);

        return response()->json(['data' => $this->payload($appointment)]);
    }

    /**
     * Fiche praticien de l'acteur s'il n'est PAS gestionnaire (direction /
     * réception) — sinon null (aucune restriction de périmètre).
     */
    private function restrictedPractitionerId(Employee $actor): ?int
    {
        if (HealthAccess::isAdmin($actor) || HealthAccess::isReception($actor)) {
            return null;
        }

        return HealthAccess::practitionerId($actor);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthAppointment $appointment): array
    {
        return [
            'id' => (int) $appointment->getAttribute('id'),
            'patient_id' => $appointment->patient_id,
            'practitioner_id' => $appointment->practitioner_id,
            'department_id' => $appointment->department_id,
            'starts_at' => $appointment->starts_at->toIso8601String(),
            'ends_at' => $appointment->ends_at->toIso8601String(),
            'reason' => $appointment->reason,
            'status' => $appointment->status,
            'notes' => $appointment->notes,
        ];
    }
}
