<?php

declare(strict_types=1);

namespace App\AI\Workflows;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WeeklyReportWorkflow
{
    /**
     * @return array{period: array{start: string, end: string}, headcount: array<string, mixed>, absences: array<string, mixed>, anomalies: array<int, array<string, mixed>>, summary: string}
     */
    public function execute(string $companyId, ?string $weekStart = null): array
    {
        $start = $weekStart
            ? Carbon::parse($weekStart)->startOfWeek()
            : Carbon::now()->subWeek()->startOfWeek();
        $end = (clone $start)->endOfWeek();

        $startStr = $start->toDateString();
        $endStr = $end->toDateString();

        $headcount = $this->buildHeadcount($companyId);
        $absences = $this->buildAbsenceSummary($companyId, $startStr, $endStr);
        $anomalies = $this->detectAnomalies($companyId, $startStr, $endStr);

        $totalEmployees = $headcount['total'];
        $totalAbsences = $absences['total'];
        $absenceRate = $totalEmployees > 0
            ? round(($totalAbsences / $totalEmployees) * 100, 1)
            : 0;

        $summary = sprintf(
            'Semaine du %s au %s : %d employes actifs, %d absences (%s%%), %d anomalies detectees.',
            $startStr,
            $endStr,
            $totalEmployees,
            $totalAbsences,
            $absenceRate,
            count($anomalies),
        );

        Log::channel('structured')->info('AI workflow: weekly report', [
            'company_id' => $companyId,
            'period' => $startStr.' - '.$endStr,
            'headcount' => $totalEmployees,
            'absences' => $totalAbsences,
            'anomalies' => count($anomalies),
        ]);

        return [
            'period' => ['start' => $startStr, 'end' => $endStr],
            'headcount' => $headcount,
            'absences' => $absences,
            'anomalies' => $anomalies,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{total: int, by_department: list<array{department: string, count: int}>, by_status: list<array{status: string, count: int}>}
     */
    private function buildHeadcount(string $companyId): array
    {
        $total = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->count();

        /** @var list<array{department: string, count: int}> $byDepartment */
        $byDepartment = DB::table('employees')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('employees.company_id', $companyId)
            ->where('employees.status', 'active')
            ->groupBy('departments.name')
            ->select('departments.name as department', DB::raw('count(*) as count'))
            ->orderByDesc('count')
            ->get()
            ->map(function (\stdClass $row): array {
                /** @var string $dept */
                $dept = $row->department;
                /** @var int|string $cnt */
                $cnt = $row->count;

                return ['department' => $dept, 'count' => (int) $cnt];
            })
            ->values()
            ->all();

        /** @var list<array{status: string, count: int}> $byStatus */
        $byStatus = DB::table('employees')
            ->where('company_id', $companyId)
            ->groupBy('status')
            ->select('status', DB::raw('count(*) as count'))
            ->get()
            ->map(function (\stdClass $row): array {
                /** @var string $status */
                $status = $row->status;
                /** @var int|string $cnt */
                $cnt = $row->count;

                return ['status' => $status, 'count' => (int) $cnt];
            })
            ->values()
            ->all();

        return [
            'total' => $total,
            'by_department' => $byDepartment,
            'by_status' => $byStatus,
        ];
    }

    /**
     * @return array{total: int, by_type: list<array{type: string, count: int}>, pending: int, approved: int, rejected: int}
     */
    private function buildAbsenceSummary(string $companyId, string $startDate, string $endDate): array
    {
        $query = DB::table('absences')
            ->where('company_id', $companyId)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        $total = (clone $query)->count();

        /** @var list<array{type: string, count: int}> $byType */
        $byType = (clone $query)
            ->groupBy('type')
            ->select('type', DB::raw('count(*) as count'))
            ->get()
            ->map(function (\stdClass $row): array {
                /** @var string $type */
                $type = $row->type;
                /** @var int|string $cnt */
                $cnt = $row->count;

                return ['type' => $type, 'count' => (int) $cnt];
            })
            ->values()
            ->all();

        $pending = (clone $query)->where('status', 'pending')->count();
        $approved = (clone $query)->where('status', 'approved')->count();
        $rejected = (clone $query)->where('status', 'rejected')->count();

        return [
            'total' => $total,
            'by_type' => $byType,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
        ];
    }

    /**
     * @return array<int, array{type: string, detail: string, severity: string}>
     */
    private function detectAnomalies(string $companyId, string $startDate, string $endDate): array
    {
        $anomalies = [];

        $noCheckins = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereNotExists(function (Builder $q) use ($startDate, $endDate): void {
                $q->select(DB::raw(1))
                    ->from('attendance_logs')
                    ->whereColumn('attendance_logs.employee_id', 'employees.id')
                    ->whereBetween('attendance_logs.date', [$startDate, $endDate]);
            })
            ->whereNotExists(function (Builder $q) use ($startDate, $endDate): void {
                $q->select(DB::raw(1))
                    ->from('absences')
                    ->whereColumn('absences.employee_id', 'employees.id')
                    ->where('absences.status', 'approved')
                    ->where('absences.start_date', '<=', $endDate)
                    ->where('absences.end_date', '>=', $startDate);
            })
            ->count();

        if ($noCheckins > 0) {
            $anomalies[] = [
                'type' => 'no_attendance_no_absence',
                'detail' => $noCheckins.' employes sans pointage ni absence approuvee cette semaine',
                'severity' => 'high',
            ];
        }

        $expiringContracts = DB::table('contracts')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereBetween('end_date', [$endDate, Carbon::parse($endDate)->addDays(30)->toDateString()])
            ->count();

        if ($expiringContracts > 0) {
            $anomalies[] = [
                'type' => 'contracts_expiring_soon',
                'detail' => $expiringContracts.' contrats expirent dans les 30 prochains jours',
                'severity' => 'medium',
            ];
        }

        return $anomalies;
    }
}
