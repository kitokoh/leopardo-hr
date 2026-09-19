<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Access\HealthAccess;
use App\Modules\HealthManager\Domain\Exceptions\HealthAppointmentConflictException;
use App\Modules\HealthManager\Domain\Exceptions\HealthInvalidStatusTransitionException;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthAppointmentRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthAppointmentRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthAppointmentStatusRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * API des rendez-vous & agenda praticiens — HC-004 (#7788, BC-30).
 *
 * Direction et accueil planifient et voient tout ; un praticien actif ne
 * voit que SON agenda (policy) et fait avancer le statut de SES rendez-vous.
 * Détection de conflit de créneau PAR PRATICIEN dans une transaction
 * lockForUpdate → 409 HEALTH_APPOINTMENT_CONFLICT ; transitions de statut
 * validées par la machine à états → 422 HEALTH_INVALID_STATUS_TRANSITION.
 */
class HealthAppointmentController extends Controller
{
    use ChecksHealthSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthAppointment::class);

        $query = HealthAppointment::query()->where('company_id', $actor->company_id);

        // « Le praticien ne voit que son agenda » : un acteur praticien
        // NON gestionnaire est borné à ses propres rendez-vous.
        if (! HealthAccess::canManageAppointments($actor)) {
            $query->where('practitioner_id', (int) HealthAccess::practitionerId($actor));
        } elseif ($request->filled('practitioner_id')) {
            $query->where('practitioner_id', (int) $request->input('practitioner_id'));
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', (int) $request->input('patient_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('from')) {
            $query->where('starts_at', '>=', Carbon::parse((string) $request->input('from')));
        }
        if ($request->filled('to')) {
            $query->where('starts_at', '<', Carbon::parse((string) $request->input('to')));
        }

        $appointments = $query->orderBy('starts_at')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($appointments->items())->map(fn (HealthAppointment $appointment): array => $this->payload($appointment)),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ],
        ]);
    }

    /**
     * Agenda praticien par jour ou par semaine (HC-004). Un praticien non
     * gestionnaire ne consulte que SON agenda (403 sinon) ; direction et
     * accueil consultent l'agenda de n'importe quel praticien.
     */
    public function agenda(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthAppointment::class);

        $ownPractitionerId = HealthAccess::practitionerId($actor);
        $requestedId = $request->filled('practitioner_id')
            ? (int) $request->input('practitioner_id')
            : $ownPractitionerId;

        if ($requestedId === null) {
            throw ValidationException::withMessages([
                'practitioner_id' => ['Le praticien est requis pour consulter un agenda.'],
            ]);
        }

        if (! HealthAccess::canManageAppointments($actor) && $requestedId !== $ownPractitionerId) {
            abort(403);
        }

        $view = (string) $request->input('view', 'day');
        if (! in_array($view, ['day', 'week'], true)) {
            throw ValidationException::withMessages([
                'view' => ['Vue d\'agenda invalide (day ou week).'],
            ]);
        }

        $date = $request->filled('date')
            ? Carbon::parse((string) $request->input('date'))
            : now();
        $from = $view === 'week' ? $date->copy()->startOfWeek() : $date->copy()->startOfDay();
        $to = $view === 'week' ? $date->copy()->endOfWeek() : $date->copy()->endOfDay();

        $appointments = HealthAppointment::query()
            ->where('company_id', $actor->company_id)
            ->where('practitioner_id', $requestedId)
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<=', $to)
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'data' => $appointments->map(fn (HealthAppointment $appointment): array => $this->payload($appointment)),
            'meta' => [
                'practitioner_id' => $requestedId,
                'view' => $view,
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
                'total' => $appointments->count(),
            ],
        ]);
    }

    public function store(StoreHealthAppointmentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthAppointment::class);

        $validated = $request->validated();

        // Conflit de créneau vérifié DANS la transaction (lockForUpdate sur
        // les rendez-vous actifs du praticien) → 409 si chevauchement.
        /** @var HealthAppointment $appointment */
        $appointment = DB::transaction(function () use ($validated, $actor): HealthAppointment {
            $this->assertNoConflict(
                $actor->company_id,
                (int) $validated['practitioner_id'],
                Carbon::parse((string) $validated['starts_at']),
                Carbon::parse((string) $validated['ends_at']),
            );

            /** @var HealthAppointment $created */
            $created = new HealthAppointment($validated);
            $created->save();

            return $created;
        });

        return response()->json(['data' => $this->payload($appointment)], 201);
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

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $appointment, $actor): void {
            $appointment->fill($validated);

            // Cohérence du créneau APRÈS fusion (le CHECK en base est le
            // filet, la 422 est la réponse contractuelle).
            if ($appointment->ends_at->lessThanOrEqualTo($appointment->starts_at)) {
                throw ValidationException::withMessages([
                    'ends_at' => ['La fin du rendez-vous doit suivre son commencement.'],
                ]);
            }

            $this->assertNoConflict(
                $actor->company_id,
                $appointment->practitioner_id,
                $appointment->starts_at,
                $appointment->ends_at,
                $appointment->id,
            );

            $appointment->save();
        });

        return response()->json(['data' => $this->payload($appointment->refresh())]);
    }

    /**
     * Transition de statut validée par la machine à états (HC-004) :
     * toute transition hors HealthAppointment::TRANSITIONS → 422.
     */
    public function updateStatus(UpdateHealthAppointmentStatusRequest $request, HealthAppointment $appointment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($appointment, $actor->company_id);
        $this->authorize('transition', $appointment);

        $target = (string) $request->validated()['status'];

        if (! $appointment->canTransitionTo($target)) {
            throw new HealthInvalidStatusTransitionException($appointment->status, $target);
        }

        $appointment->status = $target;
        $appointment->save();

        return response()->json(['data' => $this->payload($appointment)]);
    }

    public function destroy(Request $request, HealthAppointment $appointment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($appointment, $actor->company_id);
        $this->authorize('delete', $appointment);

        $appointment->delete();

        return response()->json(null, 204);
    }

    /**
     * Chevauchement par praticien : un rendez-vous ACTIF (scheduled,
     * confirmed, checked_in) du même praticien dont [starts_at, ends_at)
     * intersecte le créneau demandé → 409. Les rendez-vous annulés ou non
     * honorés libèrent le créneau. lockForUpdate : deux réservations
     * concurrentes du même créneau se sérialisent.
     */
    private function assertNoConflict(
        string $companyId,
        int $practitionerId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $ignoreId = null,
    ): void {
        $conflict = HealthAppointment::query()
            ->where('company_id', $companyId)
            ->where('practitioner_id', $practitionerId)
            ->whereIn('status', HealthAppointment::ACTIVE_STATUSES)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new HealthAppointmentConflictException;
        }
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
            'starts_at' => $appointment->starts_at->toISOString(),
            'ends_at' => $appointment->ends_at->toISOString(),
            'reason' => $appointment->reason,
            'status' => $appointment->status,
            'notes' => $appointment->notes,
        ];
    }
}
