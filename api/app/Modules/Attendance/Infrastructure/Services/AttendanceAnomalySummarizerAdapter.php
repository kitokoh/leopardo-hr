<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Shared\Contracts\Attendance\AttendanceAnomalySummarizer;
use Illuminate\Support\Facades\Gate;

/**
 * Adapter du contrat partagé `AttendanceAnomalySummarizer` (#8299, BOS-023
 * cycle 3).
 *
 * Reprend à l'identique la séquence qu'exécutait historiquement
 * `MeController::attendanceAnomalies` (HR) : autorisation
 * `viewOwnAnomalies` sur la classe du modèle (403 inchangé), puis fusion
 * forcée de `employee_id` = appelant, puis délégation à
 * `AttendanceAnomalyService::summarize` avec scopeActor null.
 */
final class AttendanceAnomalySummarizerAdapter implements AttendanceAnomalySummarizer
{
    public function __construct(
        private readonly AttendanceAnomalyService $anomalyService,
    ) {}

    public function summarizeOwnAnomalies(Employee $employee, array $filters): array
    {
        Gate::authorize('viewOwnAnomalies', AttendanceLog::class);

        $filters = array_merge($filters, ['employee_id' => $employee->id]);

        return $this->anomalyService->summarize((string) $employee->company_id, $filters, null);
    }
}
