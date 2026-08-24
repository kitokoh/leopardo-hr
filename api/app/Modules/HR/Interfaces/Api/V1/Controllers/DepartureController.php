<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HR\Domain\Models\EmployeeDeparture;
use App\Modules\HR\Infrastructure\Services\DepartureService;
use App\Modules\HR\Interfaces\Api\V1\Requests\StoreDepartureRequest;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Workflow de départ (issue #5324) — enregistrement + consultation.
 *
 * L'écriture (`store`) orchestre le départ côté HR : dossier
 * `employee_departures` + statut `departed` + révocation d'accès. Le solde
 * de tout compte et l'attestation sont générés par le module Payroll
 * (`GET /employees/{id}/end-of-contract`, `/certificate-of-employment`) —
 * l'utilisateur les appelle après l'enregistrement (workflow ordonné).
 */
class DepartureController extends Controller
{
    public function __construct(private readonly DepartureService $departureService) {}

    public function store(StoreDepartureRequest $request, Employee $employee): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        // Binding implicite {employee} → $employee (leçon route-binding par
        // nom : un paramètre nommé différemment recevrait une instance vide).
        $this->authorize('departure', $employee);

        try {
            $departure = $this->departureService->registerDeparture($actor, $employee, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse([
                'message' => __($exception->getMessage()),
            ], 422);
        }

        return new JsonResponse([
            'data' => $this->payload($departure, $employee),
            'message' => __('employees.departure_registered'),
        ], 201);
    }

    /** Lecture d'un départ (manager : son entreprise ; employé : le sien). */
    public function show(Employee $employee): JsonResponse
    {
        /** @var Employee $actor */
        $actor = request()->user();

        if ($actor->id === $employee->id) {
            $departure = EmployeeDeparture::query()
                ->where('employee_id', $employee->id)
                ->latest('id')
                ->first();

            return $departure !== null
                ? new JsonResponse(['data' => $this->payload($departure, $employee)])
                : new JsonResponse(['data' => null]);
        }

        if ($employee->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $employee);

        $departure = EmployeeDeparture::query()
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->first();

        return new JsonResponse(['data' => $departure !== null ? $this->payload($departure, $employee) : null]);
    }

    /** Self-service : mon départ (dernier enregistré). */
    public function myDeparture(): JsonResponse
    {
        /** @var Employee $employee */
        $employee = request()->user();

        $departure = EmployeeDeparture::query()
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->first();

        return new JsonResponse(['data' => $departure !== null ? $this->payload($departure, $employee) : null]);
    }

    /** @return array<string, mixed> */
    private function payload(EmployeeDeparture $departure, Employee $employee): array
    {
        return [
            'id' => $departure->id,
            'employee_id' => $departure->employee_id,
            'employee_status' => $employee->status,
            'departure_type' => $departure->departure_type,
            'reason' => $departure->reason,
            'last_work_day' => $departure->last_work_day?->toDateString(),
            'notice_served' => $departure->notice_served,
            'notice_days_served' => $departure->notice_days_served,
            'departed_at' => $departure->departed_at?->toDateString(),
            'created_at' => $departure->created_at?->toIso8601String(),
        ];
    }
}
