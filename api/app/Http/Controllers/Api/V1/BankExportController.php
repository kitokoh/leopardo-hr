<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BankExport;
use App\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankExportController extends Controller
{
    public function generate(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        if (! in_array($payrollRun->status, ['validated', 'paid'])) {
            return response()->json(['message' => 'Payroll run must be validated before generating bank export.'], 422);
        }

        $validated = $request->validate([
            'format' => 'required|in:sepa_xml,ccp_dz,virement_ma,csv_generic',
        ]);

        $slips = $payrollRun->paySlips()
            ->with('employee:id,first_name,last_name')
            ->where('status', 'validated')
            ->get();

        $fileName = sprintf('bank_exports/%s_%s_%s.csv',
            $payrollRun->company_id,
            $payrollRun->period_start->format('Y_m'),
            $validated['format']
        );

        $csvContent = "employee_id,first_name,last_name,net_salary\n";
        $totalAmount = 0.0;
        foreach ($slips as $slip) {
            $csvContent .= sprintf("%d,%s,%s,%.2f\n",
                $slip->employee_id,
                $slip->employee->first_name ?? '',
                $slip->employee->last_name ?? '',
                $slip->net_salary
            );
            $totalAmount += $slip->net_salary;
        }

        Storage::disk('local')->put($fileName, $csvContent);

        $export = BankExport::create([
            'payroll_run_id' => $payrollRun->id,
            'company_id' => $payrollRun->company_id,
            'format' => $validated['format'],
            'file_path' => $fileName,
            'total_amount' => round($totalAmount, 2),
            'transfer_count' => $slips->count(),
            'status' => 'generated',
            'generated_at' => now(),
        ]);

        return response()->json(['data' => $export], 201);
    }

    public function show(Request $request, BankExport $bankExport): JsonResponse
    {
        $actor = $request->user();
        if ($bankExport->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $bankExport->load('payrollRun:id,period_start,period_end,status');

        return response()->json(['data' => $bankExport]);
    }

    public function download(Request $request, BankExport $bankExport): StreamedResponse|JsonResponse
    {
        $actor = $request->user();
        if ($bankExport->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        if (! Storage::disk('local')->exists($bankExport->file_path)) {
            return response()->json(['message' => 'Export file not found.'], 404);
        }

        return Storage::disk('local')->download($bankExport->file_path);
    }
}
