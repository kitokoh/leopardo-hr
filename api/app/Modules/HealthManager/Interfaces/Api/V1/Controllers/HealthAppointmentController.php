<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Infrastructure\Services\HealthAppointmentService;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthAppointmentRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\TransitionHealthAppointmentRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthAppointmentRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * API des rendez-vous & agenda — HC-004 (#7788, BC-30).
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

    public function __construct(private readonly HealthAppointmentService $service) {}

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

        $payload = $request->validated();
        $companyId = (string) $actor->company_id;
        $startsAt = Carbon::parse((string) $payload['starts_at']);
        $endsAt = Carbon::parse((string) $payload['ends_at']);

        $appointment = DB::transaction(function () use ($payload, $companyId, $startsAt, $endsAt): HealthAppointment {
            // 409 HEALTH_APPOINTMENT_CONFLICT si chevauchement praticien.
            $this->service->assertNoConflict($companyId, (int) $payload['practitioner_id'], $startsAt, $endsAt);

            /** @var HealthAppointment $appointment */
            $appointment = HealthAppointment::query()->create(array_merge($payload, [
                'company_id' => $companyId,
                'status' => HealthAppointment::STATUS_SCHEDULED,
            ]));

            return $appointment;
        });

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

        $payload = $request->validated();

        $startsAt = isset($payload['starts_at'])
            ? Carbon::parse((string) $payload['starts_at'])
            : $appointment->starts_at;
        $endsAt = isset($payload['ends_at'])
            ? Carbon::parse((string) $payload['ends_at'])
            : $appointment->ends_at;

        // Cohérence temporelle re-vérifiée sur l'état FUSIONNÉ (un seul des
        // deux champs peut bouger) → 422.
        abort_if($endsAt->lessThanOrEqualTo($startsAt), 422, 'ends_at doit être postérieur à starts_at.');

        $practitionerId = isset($payload['practitioner_id'])
            ? (int) $payload['practitioner_id']
            : $appointment->practitioner_id;

        DB::transaction(function () use ($appointment, $payload, $actor, $practitionerId, $startsAt, $endsAt): void {
            // 409 si le créneau (éventuellement déplacé) chevauche un autre
            // rendez-vous actif du praticien — le sien est ignoré.
            $this->service->assertNoConflict(
                (string) $actor->company_id,
                $practitionerId,
                $startsAt,
                $endsAt,
                (int) $appointment->getAttribute('id'),
            );

            $appointment->update($payload);
        });

        return response()->json(['data' => $this->payload($appointment->refresh())]);
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

        // 422 HEALTH_INVALID_TRANSITION hors machine à états.
        $this->service->assertValidTransition($appointment->status, $target);

        $appointment->update(['status' => $target]);

        return response()->json(['data' => $this->payload($appointment->refresh())]);
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
