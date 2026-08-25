<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Domain\Models\AttendanceDayClosure;
use App\Modules\Attendance\Infrastructure\Services\AttendanceDayClosureService;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\ListDayClosureRequest;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\StoreDayClosureRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fermeture de journée du pointage (issue #5265) — manager/RH/principal.
 *
 * Verrouillage quotidien par employé : un jour clos refuse tout nouveau
 * pointage (409 ATTENDANCE_DAY_CLOSED). La validation est un acte distinct
 * (review manager). Complémentaire du verrouillage de période #5267.
 *
 * Middleware: auth:sanctum + tenant + api.manager:rh,principal
 */
class AttendanceDayClosureController extends Controller
{
    public function __construct(
        private readonly AttendanceDayClosureService $dayClosureService,
    ) {}

    /**
     * GET /api/v1/attendance/day-closures?date=YYYY-MM-DD&employee_id=N
     * Liste les fermetures de journée de l'entreprise (filtrable).
     */
    public function index(ListDayClosureRequest $request): JsonResponse
    {
        $company = currentCompany();

        $date = $request->validated('date');
        $employeeId = $request->validated('employee_id');

        $closures = $this->dayClosureService->listFor(
            companyId: $company->id,
            date: is_string($date) ? $date : null,
            employeeId: is_numeric($employeeId) ? (int) $employeeId : null,
        );

        return new JsonResponse([
            'data' => $closures->map(fn (AttendanceDayClosure $closure): array => $this->payload($closure))->values(),
        ]);
    }

    /**
     * POST /api/v1/attendance/day-closures
     * Verrouille la journée d'un employé (idempotent).
     */
    public function store(StoreDayClosureRequest $request): JsonResponse
    {
        $company = currentCompany();

        /** @var Employee $actor */
        $actor = $request->user();

        /** @var Employee $employee */
        $employee = Employee::query()
            ->where('company_id', $company->id)
            ->findOrFail((int) $request->validated('employee_id'));

        $date = $request->validated('date');
        $note = $request->validated('note');

        $closure = $this->dayClosureService->lockDay(
            employee: $employee,
            date: is_string($date) ? $date : '',
            actor: $actor,
            note: is_string($note) ? $note : null,
        );

        return (new JsonResponse(['data' => $this->payload($closure)]))
            ->setStatusCode(201);
    }

    /**
     * POST /api/v1/attendance/day-closures/{id}/validate
     * Valide une journée verrouillée (review manager/RH).
     *
     * Nommée markValidated pour ne pas écraser Controller::validate()
     * (ValidatesRequests — signature incompatible = fatal error PHP).
     */
    public function markValidated(Request $request, int $id): JsonResponse
    {
        $company = currentCompany();

        /** @var Employee $actor */
        $actor = $request->user();

        $closure = $this->findClosure($company->id, $id);

        $note = $request->input('note');

        $validated = $this->dayClosureService->validateDay($closure, $actor, is_string($note) ? $note : null);

        return new JsonResponse(['data' => $this->payload($validated)]);
    }

    /**
     * DELETE /api/v1/attendance/day-closures/{id}
     * Lève le verrou (la journée redevient ouverte aux pointages).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $company = currentCompany();

        $closure = $this->findClosure($company->id, $id);

        $this->dayClosureService->unlockDay($closure);

        return new JsonResponse(null, 204);
    }

    /**
     * Résout une fermeture dans l'entreprise courante — 404 cross-tenant.
     */
    private function findClosure(string $companyId, int $id): AttendanceDayClosure
    {
        /** @var AttendanceDayClosure $closure */
        $closure = AttendanceDayClosure::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return $closure;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AttendanceDayClosure $closure): array
    {
        return [
            'id' => $closure->id,
            'employee_id' => $closure->employee_id,
            'employee' => $closure->employee !== null ? [
                'id' => $closure->employee->id,
                'first_name' => $closure->employee->first_name,
                'last_name' => $closure->employee->last_name,
            ] : null,
            'date' => $closure->date->toDateString(),
            'status' => $closure->status,
            'locked_by' => $closure->locked_by,
            'locked_at' => $closure->locked_at?->toIso8601String(),
            'validated_by' => $closure->validated_by,
            'validated_at' => $closure->validated_at?->toIso8601String(),
            'note' => $closure->note,
        ];
    }
}
