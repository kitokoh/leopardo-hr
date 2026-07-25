<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PayrollRunResource;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollCycleService;
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
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        // PA2-API-001: this endpoint used to return Laravel's raw paginator
        // shape (top-level current_page/data/links/...), which diverges from
        // the success/data/meta envelope used by every other paginated list
        // in the API (see PayrollRunController::index, EmployeeController::index,
        // etc). Route through the same resource used by /payroll-runs so
        // /payroll/cycles matches the standard contract.
        return PayrollRunResource::collection($runs)->response();
    }

    /**
     * PA2-PAY-011 — GET /payroll/cycle-settings: expose the company's
     * configurable pay cycle rule (daily/weekly/monthly, pay day, week start)
     * to managers so they can review it before changing it.
     */
    public function cycleSettings(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $company = $actor->company;
        if (! $company instanceof Company) {
            abort(404);
        }

        return response()->json([
            'data' => $this->cycleService->getPayCycleSettings($company),
        ]);
    }

    /**
     * PA2-PAY-011 — PUT /payroll/cycle-settings: let a manager configure the
     * company-wide pay cycle rule (journalier/hebdomadaire/mensuel), pay day,
     * and week start. Applies immediately to getCurrentCycle()/balances.
     */
    public function updateCycleSettings(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $company = $actor->company;
        if (! $company instanceof Company) {
            abort(404);
        }

        $validated = $request->validate([
            'pay_cycle' => ['sometimes', 'string', 'in:daily,weekly,monthly'],
            'pay_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'week_start' => ['sometimes', 'integer', 'min:1', 'max:7'],
        ]);

        $settings = $this->cycleService->updatePayCycleSettings($company, $validated);

        return response()->json([
            'data' => $settings,
            'message' => 'Cycle de paie mis a jour.',
        ]);
    }

    /**
     * PA2-PAY-003 — GET /payroll/cycles/preview: lets a manager preview the
     * resulting period and an estimated payroll total for a candidate pay
     * cycle rule (journalier/hebdomadaire/mensuel, pay day, week start)
     * before committing it via PUT /payroll/cycle-settings. Read-only, does
     * not persist anything.
     */
    public function preview(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $company = $actor->company;
        if (! $company instanceof Company) {
            abort(404);
        }

        $overrides = $request->validate([
            'pay_cycle' => ['sometimes', 'string', 'in:daily,weekly,monthly'],
            'pay_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'week_start' => ['sometimes', 'integer', 'min:1', 'max:7'],
        ]);

        return response()->json([
            'data' => $this->cycleService->previewCycle($company, $overrides),
        ]);
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

    public function employeeBalance(Request $request, int $employee): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->id !== $employee && ! $actor->isManager()) {
            abort(403);
        }

        /** @var Employee|null $targetEmployee */
        $targetEmployee = Employee::query()
            ->where('company_id', $actor->company_id)
            ->find($employee);

        if ($targetEmployee === null) {
            abort(404);
        }

        $payload = $this->cycleService->getEmployeeBalance($targetEmployee);

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
