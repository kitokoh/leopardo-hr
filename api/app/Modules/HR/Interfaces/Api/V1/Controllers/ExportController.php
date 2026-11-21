<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger;
use App\Http\Controllers\Controller;
use App\Modules\HR\Domain\Models\ExportHistory;
use App\Modules\HR\Infrastructure\Services\HrExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    public function __construct(
        private readonly DataAccessAuditLogger $auditLogger,
        private readonly HrExportService $exports,
    ) {}

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

        return $this->exports->exportWithFilename(
            $request,
            $employees,
            'employees',
            'employees_export_'.now()->format('Y-m-d').'.csv',
        );
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

        return $this->exports->exportWithFilename(
            $request,
            $logs,
            'attendance',
            'attendance_export_'.$from.'_'.$to.'.csv',
        );
    }

    public function paySlips(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $records = $this->exports->tableForCompany('pay_slips', $user->company_id, [
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

        return $this->exports->exportResponse($request, $records, 'pay_slips_export');
    }

    public function absences(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $records = $this->exports->tableForCompany('absences', $user->company_id, [
            'id',
            'employee_id',
            'absence_type_id',
            'start_date',
            'end_date',
            'status',
            'reason',
            'created_at',
        ]);

        return $this->exports->exportResponse($request, $records, 'absences_export');
    }

    public function training(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $records = $this->exports->tableForCompany('training_enrollments', $user->company_id, [
            'id',
            'employee_id',
            'session_id',
            'status',
            'progress',
            'score',
            'created_at',
        ]);

        return $this->exports->exportResponse($request, $records, 'training_export');
    }

    public function contracts(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $records = $this->exports->tableForCompany('contracts', $user->company_id, [
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

        return $this->exports->exportResponse($request, $records, 'contracts_export');
    }

    public function vehicles(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $records = $this->exports->tableForCompany('vehicles', $user->company_id, [
            'id',
            'plate_number',
            'brand',
            'model',
            'status',
            'km_current',
            'assigned_driver_id',
            'created_at',
        ]);

        return $this->exports->exportResponse($request, $records, 'vehicles_export', 'vehicles');
    }

    public function history(Request $request): JsonResponse
    {        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'type' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = ExportHistory::query()
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at');

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        /** @var LengthAwarePaginator<int, ExportHistory> $history */
        $history = $query->paginate((int) ($validated['per_page'] ?? 20));

        $items = collect($history->items())->map(static fn (ExportHistory $row): array => [
            'id' => $row->id,
            'type' => $row->type,
            'format' => $row->format,
            'record_count' => $row->record_count,
            'filename' => $row->filename,
            'created_at' => $row->created_at->toIso8601String(),
        ])->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
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

        $this->auditLogger->recordSensitive($request, $user, 'payroll.accounting_export', null, ['report' => 'payroll_journal']);

        $records = $this->exports->tableForCompany('pay_slips', $user->company_id, [
            'id', 'employee_id', 'payroll_run_id', 'gross_salary', 'net_salary', 'status', 'period_start', 'period_end',
        ]);

        return $this->exports->exportResponse($request, $records, 'payroll_journal');
    }

    public function accountingLedger(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $this->auditLogger->recordSensitive($request, $user, 'payroll.accounting_export', null, ['report' => 'payroll_ledger']);

        // Summary representation for ledger
        $records = $this->exports->tableForCompany('pay_slips', $user->company_id, [
            'payroll_run_id', 'gross_salary', 'net_salary',
        ]);

        return $this->exports->exportResponse($request, $this->exports->ledgerRows($records), 'payroll_ledger');
    }

    public function accountingOD(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $this->auditLogger->recordSensitive($request, $user, 'payroll.accounting_export', null, ['report' => 'accounting_od']);

        $records = $this->exports->tableForCompany('pay_slips', $user->company_id, [
            'payroll_run_id', 'gross_salary', 'net_salary', 'period_end',
        ]);

        return $this->exports->exportResponse($request, $this->exports->odEntries($records), 'accounting_od');
    }
}
