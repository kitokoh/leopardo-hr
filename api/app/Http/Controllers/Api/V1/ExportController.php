<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    public function employees(Request $request): JsonResponse
    {
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
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'nullable|in:json,csv',
        ]);

        $logs = DB::table('attendance_logs')
            ->where('company_id', $user->company_id)
            ->whereBetween('check_in', [$validated['from'], $validated['to'].' 23:59:59'])
            ->select(['id', 'employee_id', 'check_in', 'check_out', 'status', 'method', 'source_device_code'])
            ->orderBy('check_in')
            ->get();

        $format = $validated['format'] ?? 'json';

        if ($format === 'csv') {
            return response()->json([
                'data' => [
                    'format' => 'csv',
                    'content' => $this->toCsv($logs),
                    'filename' => 'attendance_export_'.$validated['from'].'_'.$validated['to'].'.csv',
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

                return str_contains($str, ',') ? '"'.$str.'"' : $str;
            }, array_values((array) $row));
            $csv .= implode(',', $values)."\n";
        }

        return $csv;
    }
}
