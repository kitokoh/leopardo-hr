<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Modules\HR\Infrastructure\Services\DepartureNoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Récapitulatif du préavis légal (issue #5325, gap G2).
 *
 * LECTURE SEULE : affiche la durée légale de préavis par pays/ancienneté
 * (règle Payroll consommée, jamais recalculée) + le statut de service
 * (employee_departures, workflow #5324).
 */
class DepartureNoticeController extends Controller
{
    public function __construct(
        private readonly DepartureNoticeService $service,
        private readonly DataAccessAuditLogger $auditLogger,
    ) {}

    public function show(Request $request, Employee $employee): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($employee->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false) {
            abort(403);
        }

        $this->auditLogger->recordSensitive($request, $actor, 'hr.departure_notice_viewed', $employee, [
            'resource' => 'employee_departure_notice',
            'target_employee_id' => $employee->id,
        ]);

        return response()->json([
            'data' => $this->service->summaryFor($employee),
        ]);
    }
}
