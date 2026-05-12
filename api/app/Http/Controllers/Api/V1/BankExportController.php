<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Http\Controllers\Controller;
use App\Models\BankExport;
use App\Models\PayrollRun;
use App\Services\BankExportGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankExportController extends Controller
{
    public function generate(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
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

        $generator = new BankExportGenerator();
        $format = $validated['format'];
        $content = $generator->generate($payrollRun, $format);
        $extension = $generator->fileExtension($format);

        $fileName = sprintf('bank_exports/%s_%s_%s.%s',
            $payrollRun->company_id,
            $payrollRun->period_start->format('Y_m'),
            $format,
            $extension
        );

        Storage::disk('local')->put($fileName, $content);

        $totalAmount = $payrollRun->paySlips()->where('status', 'validated')->sum('net_salary');

        $export = BankExport::create([
            'payroll_run_id' => $payrollRun->id,
            'company_id' => $payrollRun->company_id,
            'format' => $format,
            'file_path' => $fileName,
            'total_amount' => round($totalAmount, 2),
            'transfer_count' => $payrollRun->paySlips()->where('status', 'validated')->count(),
            'status' => 'generated',
            'generated_at' => now(),
        ]);

        return response()->json(['data' => $export], 201);
    }

    public function show(Request $request, BankExport $bankExport): JsonResponse
    {
        /** @var Employee $actor */
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
        /** @var Employee $actor */
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
