<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExportController extends Controller
{
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

        return $this->exportResponse($request, $records, 'vehicles_export');
    }

    public function history(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        return response()->json(['data' => []]);
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
