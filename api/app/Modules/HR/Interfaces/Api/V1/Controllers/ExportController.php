<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExportController extends Controller
{
    /**
     * Resources journalisées en accès sensible et exposées par
     * GET /export/history (issue #2199). Doit rester synchronisé avec la
     * liste blanche `security.sensitive_access_logging.resources`.
     */
    private const EXPORT_RESOURCES = [
        'hr.export.employees',
        'hr.export.attendance',
        'hr.export.pay_slips',
        'hr.export.absences',
        'hr.export.training',
        'hr.export.contracts',
        'hr.export.vehicles',
        'payroll.accounting_export',
    ];

    public function __construct(private readonly DataAccessAuditLogger $auditLogger) {}

    public function employees(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'nullable|in:json,csv',
            'status' => 'nullable|in:active,archived',
        ]);

        $query = DB::table('employees')
            ->where('company_id', $user->company_id)
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'position_id',
                'department_id',
                'status',
                'contract_start',
                'created_at',
            ]);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $employees = $query->orderBy('last_name')->get();
        $format = $validated['format'] ?? 'json';

        $this->auditLogger->recordSensitive($request, $user, 'hr.export.employees', null, ['report' => 'employees', 'format' => $format]);

        if ($format === 'csv') {
            $csv = $this->toCsv($employees);

            return response()->json([
                'data' => [
                    'format' => 'csv',
                    'content' => $csv,
                    'filename' => 'employees_export_'.now()->format('Y-m-d').'.csv',
                    'count' => $employees->count(),
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'format' => 'json',
                'records' => $employees,
                'count' => $employees->count(),
            ],
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'format' => 'nullable|in:json,csv',
        ]);

        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $logs = DB::table('attendance_logs')
            ->where('company_id', $user->company_id)
            ->whereBetween('check_in', [$from, $to.' 23:59:59'])
            ->select(['id', 'employee_id', 'check_in', 'check_out', 'status', 'method', 'source_device_code'])
            ->orderBy('check_in')
            ->get();

        $format = $validated['format'] ?? 'json';

        $this->auditLogger->recordSensitive($request, $user, 'hr.export.attendance', null, ['report' => 'attendance', 'format' => $format]);

        if ($format === 'csv') {
            return response()->json([
                'data' => [
                    'format' => 'csv',
                    'content' => $this->toCsv($logs),
                    'filename' => 'attendance_export_'.$from.'_'.$to.'.csv',
                    'count' => $logs->count(),
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'format' => 'json',
                'records' => $logs,
                'count' => $logs->count(),
            ],
        ]);
    }

    public function paySlips(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $records = $this->tableForCompany('pay_slips', $user->company_id, [
            'id',
            'employee_id',
            'payroll_run_id',
            'gross_salary',
            'net_salary',
            'status',
            'period_start',
            'period_end',
            'created_at',
        ]);

            $this->auditLogger->recordSensitive($request, $user, 'hr.export.pay_slips', null, ['report' => 'pay_slips', 'format' => $request->input('format', 'json')]);

        return $this->exportResponse($request, $records, 'pay_slips_export');
    }

    public function absences(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $records = $this->tableForCompany('absences', $user->company_id, [
            'id',
            'employee_id',
            'absence_type_id',
            'start_date',
            'end_date',
            'status',
            'reason',
            'created_at',
        ]);

            $this->auditLogger->recordSensitive($request, $user, 'hr.export.absences', null, ['report' => 'absences', 'format' => $request->input('format', 'json')]);

        return $this->exportResponse($request, $records, 'absences_export');
    }

    public function training(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $records = $this->tableForCompany('training_enrollments', $user->company_id, [
            'id',
            'employee_id',
            'session_id',
            'status',
            'progress',
            'score',
            'created_at',
        ]);

            $this->auditLogger->recordSensitive($request, $user, 'hr.export.training', null, ['report' => 'training', 'format' => $request->input('format', 'json')]);

        return $this->exportResponse($request, $records, 'training_export');
    }

    public function contracts(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $records = $this->tableForCompany('contracts', $user->company_id, [
            'id',
            'employee_id',
            'reference',
            'type',
            'status',
            'start_date',
            'end_date',
            'base_salary',
            'currency',
            'created_at',
        ]);

            $this->auditLogger->recordSensitive($request, $user, 'hr.export.contracts', null, ['report' => 'contracts', 'format' => $request->input('format', 'json')]);

        return $this->exportResponse($request, $records, 'contracts_export');
    }

    public function vehicles(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $records = $this->tableForCompany('vehicles', $user->company_id, [
            'id',
            'plate_number',
            'brand',
            'model',
            'status',
            'km_current',
            'assigned_driver_id',
            'created_at',
        ]);

            $this->auditLogger->recordSensitive($request, $user, 'hr.export.vehicles', null, ['report' => 'vehicles', 'format' => $request->input('format', 'json')]);

        return $this->exportResponse($request, $records, 'vehicles_export');
    }

    public function history(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $perPage = max(1, min(100, $request->integer('per_page', 20)));

        // Issue #2199 : l'historique s'appuie sur la piste d'audit réelle
        // (audit_logs, écrits par DataAccessAuditLogger::recordSensitive sur
        // chaque export) — plus de stub `data: []`. Tenant-scope + paginé.
        $logs = AuditLog::query()
            ->where('company_id', $user->company_id)
            ->where('action', 'sensitive_data_access')
            ->whereIn('metadata->resource', self::EXPORT_RESOURCES)
            ->with('user:id,first_name,last_name')
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $logs->map(function (AuditLog $log): array {
            $metadata = $log->metadata ?? [];

            return [
                'id' => $log->id,
                'type' => $metadata['report'] ?? ($metadata['resource'] ?? 'export'),
                'format' => $metadata['format'] ?? 'json',
                'requested_by' => $log->user !== null
                    ? trim($log->user->first_name.' '.$log->user->last_name)
                    : null,
                'created_at' => $log->created_at?->toIso8601String(),
                // Les exports sont synchrones : une ligne audit = un export
                // livré immédiatement.
                'status' => 'completed',
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    public function accountingJournal(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $records = $this->tableForCompany('pay_slips', $user->company_id, [
            'id', 'employee_id', 'payroll_run_id', 'gross_salary', 'net_salary', 'status', 'period_start', 'period_end'
        ]);

            $this->auditLogger->recordSensitive($request, $user, 'payroll.accounting_export', null, ['report' => 'payroll_journal', 'format' => $request->input('format', 'json')]);

        return $this->exportResponse($request, $records, 'payroll_journal');
    }

    public function accountingLedger(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        // Summary representation for ledger
        $records = $this->tableForCompany('pay_slips', $user->company_id, [
            'payroll_run_id', 'gross_salary', 'net_salary'
        ]);
        
        // Group by payroll_run_id for the ledger
        $grouped = $records->groupBy('payroll_run_id')->map(function ($group, $runId): \stdClass {
            $row = new \stdClass;
            $row->payroll_run_id = $runId;
            $row->total_gross = $group->sum('gross_salary');
            $row->total_net = $group->sum('net_salary');
            $row->total_deductions = $group->sum('gross_salary') - $group->sum('net_salary');

            return $row;
        })->values();

            $this->auditLogger->recordSensitive($request, $user, 'payroll.accounting_export', null, ['report' => 'payroll_ledger', 'format' => $request->input('format', 'json')]);

        return $this->exportResponse($request, collect($grouped), 'payroll_ledger');
    }

    public function accountingOD(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $records = $this->tableForCompany('pay_slips', $user->company_id, [
            'payroll_run_id', 'gross_salary', 'net_salary', 'period_end'
        ]);
        
        $grouped = $records->groupBy('payroll_run_id');
        $odEntries = collect();
        
        foreach ($grouped as $runId => $group) {
            $date = $group->first()->period_end ?? now()->toDateString();
            $totalGross = $group->sum('gross_salary');
            $totalNet = $group->sum('net_salary');
            $totalSocial = $totalGross - $totalNet;
            
            // 641 - Remuneration du personnel (Debit)
            $odEntries->push((object)[
                'date' => $date,
                'account' => '641000',
                'label' => 'Rémunérations',
                'debit' => $totalGross,
                'credit' => 0
            ]);
            
            // 431 - Securite Sociale (Credit)
            $odEntries->push((object)[
                'date' => $date,
                'account' => '431000',
                'label' => 'Charges Sociales',
                'debit' => 0,
                'credit' => $totalSocial
            ]);
            
            // 421 - Personnel remu dues (Credit)
            $odEntries->push((object)[
                'date' => $date,
                'account' => '421000',
                'label' => 'Rémunérations Dues',
                'debit' => 0,
                'credit' => $totalNet
            ]);
        }

            $this->auditLogger->recordSensitive($request, $user, 'payroll.accounting_export', null, ['report' => 'accounting_od', 'format' => $request->input('format', 'json')]);

        return $this->exportResponse($request, $odEntries, 'accounting_od');
    }

    /**
     * @param  Collection<int, \stdClass>  $collection
     */
    private function toCsv(Collection $collection): string
    {
        if ($collection->isEmpty()) {
            return '';
        }

        $headers = array_keys((array) $collection->first());
        $csv = implode(',', $headers)."\n";

        foreach ($collection as $row) {
            $values = array_map(function ($v) {
                if ($v === null) {
                    return '';
                }
                $str = (string) $v;

                $escaped = str_replace('"', '""', $str);

                return str_contains($escaped, ',') || str_contains($escaped, '"') || str_contains($escaped, "\n")
                    ? '"'.$escaped.'"'
                    : $escaped;
            }, array_values((array) $row));
            $csv .= implode(',', $values)."\n";
        }

        return $csv;
    }

    /**
     * @param  list<string>  $columns
     * @return Collection<int, \stdClass>
     */
    private function tableForCompany(string $table, string|int $companyId, array $columns): Collection
    {
        if (! Schema::hasTable($table)) {
            return collect();
        }

        $availableColumns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column)
        ));

        if ($availableColumns === []) {
            return collect();
        }

        $query = DB::table($table)->select($availableColumns);

        if (Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query->orderByDesc(Schema::hasColumn($table, 'created_at') ? 'created_at' : $availableColumns[0])
            ->limit(10000)
            ->get();
    }

    /**
     * @param  Collection<int, \stdClass>  $records
     */
    private function exportResponse(Request $request, Collection $records, string $filenamePrefix): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'nullable|in:json,csv,xlsx',
        ]);

        $format = $validated['format'] ?? 'json';
        if ($format === 'csv' || $format === 'xlsx') {
            return response()->json([
                'data' => [
                    'format' => 'csv',
                    'content' => $this->toCsv($records),
                    'filename' => $filenamePrefix.'_'.now()->format('Y-m-d').'.csv',
                    'count' => $records->count(),
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'format' => 'json',
                'records' => $records,
                'count' => $records->count(),
            ],
        ]);
    }
}
