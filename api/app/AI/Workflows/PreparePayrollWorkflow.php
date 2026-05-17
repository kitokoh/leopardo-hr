<?php

declare(strict_types=1);

namespace App\AI\Workflows;

use App\Models\Employee;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PreparePayrollWorkflow
{
    /**
     * @return array{status: string, summary: array<string, mixed>, steps: array<int, array{step: string, status: string, detail: string}>}
     */
    public function execute(int $companyId, string $periodStart, string $periodEnd): array
    {
        $steps = [];

        $employeeCount = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->count();

        $steps[] = [
            'step' => 'collect_employees',
            'status' => 'ok',
            'detail' => $employeeCount.' employes actifs trouves',
        ];

        $missingStructures = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereNull('salary_structure_id')
            ->count();

        $steps[] = [
            'step' => 'check_salary_structures',
            'status' => $missingStructures > 0 ? 'warning' : 'ok',
            'detail' => $missingStructures > 0
                ? $missingStructures.' employes sans structure salariale'
                : 'Tous les employes ont une structure salariale',
        ];

        $pendingAbsences = DB::table('absences')
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->where('start_date', '<=', $periodEnd)
            ->where('end_date', '>=', $periodStart)
            ->count();

        $steps[] = [
            'step' => 'check_pending_absences',
            'status' => $pendingAbsences > 0 ? 'warning' : 'ok',
            'detail' => $pendingAbsences > 0
                ? $pendingAbsences.' absences en attente de validation sur la periode'
                : 'Aucune absence en attente',
        ];

        $approvedAbsences = DB::table('absences')
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $periodEnd)
            ->where('end_date', '>=', $periodStart)
            ->count();

        $steps[] = [
            'step' => 'count_approved_absences',
            'status' => 'ok',
            'detail' => $approvedAbsences.' absences approuvees a deduire',
        ];

        $existingRun = PayrollRun::where('company_id', $companyId)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->first();

        if ($existingRun) {
            $steps[] = [
                'step' => 'check_existing_run',
                'status' => 'info',
                'detail' => 'Un run de paie existe deja pour cette periode (statut: '.$existingRun->status.')',
            ];
        }

        $hasWarnings = collect($steps)->contains(fn (array $s): bool => $s['status'] === 'warning');

        Log::channel('structured')->info('AI workflow: prepare payroll', [
            'company_id' => $companyId,
            'period' => $periodStart.' - '.$periodEnd,
            'employee_count' => $employeeCount,
            'warnings' => $hasWarnings,
        ]);

        return [
            'status' => $hasWarnings ? 'requires_attention' : 'ready',
            'summary' => [
                'employee_count' => $employeeCount,
                'period' => $periodStart.' - '.$periodEnd,
                'missing_structures' => $missingStructures,
                'pending_absences' => $pendingAbsences,
                'approved_absences' => $approvedAbsences,
                'existing_run' => $existingRun ? $existingRun->status : null,
            ],
            'steps' => $steps,
        ];
    }
}
