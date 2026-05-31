<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\PayrollCycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plan 61 — Cycles de paie & solde employé.
 *
 * GET /payroll/cycles          — liste les cycles (PayrollRuns)
 * GET /payroll/cycles/current  — cycle courant calculé
 * GET /employees/{id}/balance  — solde employé pour le cycle courant
 */
class PayrollCycleController extends Controller
{
    public function __construct(private readonly PayrollCycleService $cycleService) {}

    /**
     * GET /payroll/cycles
     * List all payroll runs for the authenticated company.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $runs = PayrollRun::query()
            ->where('company_id', $actor->company_id)
            ->orderByDesc('period_start')
            ->paginate($request->integer('per_page', 15));

        return response()->json($runs);
    }

    /**
     * GET /payroll/cycles/current
     * Return the current computed pay cycle for the company.
     */
    public function current(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $company = $actor->company;
        $cycle   = $this->cycleService->getCurrentCycle($company);

        return response()->json([
            'period_start' => $cycle['start']->toDateString(),
            'period_end'   => $cycle['end']->toDateString(),
            'label'        => $cycle['label'],
        ]);
    }

    /**
     * GET /employees/{id}/balance
     * Return the computed balance for an employee in the current cycle.
     */
    public function employeeBalance(Request $request, int $id): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        // Self-service or manager
        if ($actor->id !== $id && ! $actor->isManager()) {
            abort(403);
        }

        /** @var Employee|null $employee */
        $employee = Employee::query()
            ->where('company_id', $actor->company_id)
            ->find($id);

        if ($employee === null) {
            abort(404);
        }

        $balance = $this->cycleService->getEmployeeBalance($employee);

        return response()->json($balance);
    }
}
