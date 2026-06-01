<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\PayrollCycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plan 61 - cycles de paie et solde employe mobile-first.
 */
class PayrollCycleController extends Controller
{
    public function __construct(private readonly PayrollCycleService $cycleService) {}

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

    public function current(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $company = $actor->company;
        if (! $company instanceof Company) {
            abort(404);
        }

        $cycle = $this->cycleService->getCurrentCycle($company);
        $payload = [
            'period_start' => $cycle['start']->toDateString(),
            'period_end' => $cycle['end']->toDateString(),
            'label' => $cycle['label'],
        ];

        return response()->json(['data' => $payload] + $payload);
    }

    public function myBalance(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $this->cycleService->getEmployeeBalance($actor),
        ]);
    }

    public function employeeBalance(Request $request, int $id): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

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

        $payload = $this->cycleService->getEmployeeBalance($employee);

        return response()->json(['data' => $payload] + $payload);
    }

    public function mobileSummary(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $items = $this->cycleService->getMobileSummary(
            actor: $actor,
            limit: $request->integer('limit', 50),
        );

        return response()->json([
            'data' => [
                'items' => $items,
                'totals' => [
                    'gross_due' => round(array_sum(array_column($items, 'gross_due')), 2),
                    'advances' => round(array_sum(array_column($items, 'advances')), 2),
                    'paid' => round(array_sum(array_column($items, 'paid')), 2),
                    'remaining' => round(array_sum(array_column($items, 'remaining')), 2),
                ],
            ],
        ]);
    }
}
