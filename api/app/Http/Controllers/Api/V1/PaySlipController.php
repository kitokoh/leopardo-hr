<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Services\PaySlipPdfGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaySlipController extends Controller
{
    public function indexForRun(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($payrollRun->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $slips = $payrollRun->paySlips()
            ->with(['employee:id,first_name,last_name,email', 'lines'])
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $slips->items(),
            'meta' => [
                'current_page' => $slips->currentPage(),
                'last_page' => $slips->lastPage(),
                'per_page' => $slips->perPage(),
                'total' => $slips->total(),
            ],
        ]);
    }

    public function show(Request $request, PaySlip $paySlip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($paySlip->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $paySlip->employee_id !== $actor->id) {
            abort(403);
        }

        $paySlip->load(['employee:id,first_name,last_name,email', 'lines', 'payrollRun']);

        return response()->json(['data' => $paySlip]);
    }

    public function myPaySlips(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $slips = PaySlip::where('employee_id', $actor->id)
            ->where('company_id', $actor->company_id)
            ->whereIn('status', ['validated', 'sent'])
            ->with('payrollRun:id,period_start,period_end,country_code')
            ->orderByDesc('period_start')
            ->paginate($request->integer('per_page', 12));

        return response()->json([
            'data' => $slips->items(),
            'meta' => [
                'current_page' => $slips->currentPage(),
                'last_page' => $slips->lastPage(),
                'per_page' => $slips->perPage(),
                'total' => $slips->total(),
            ],
        ]);
    }

    public function myPaySlipDetail(Request $request, PaySlip $paySlip): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($paySlip->employee_id !== $actor->id || $paySlip->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! in_array($paySlip->status, ['validated', 'sent'])) {
            abort(404);
        }

        $paySlip->load('lines');

        return response()->json(['data' => $paySlip]);
    }

    public function downloadPdf(Request $request, PaySlip $paySlip): Response
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $isOwner = $paySlip->employee_id === $actor->id && $paySlip->company_id === $actor->company_id;
        $isManager = $paySlip->company_id === $actor->company_id && $actor->isManager();

        if (! $isOwner && ! $isManager) {
            abort(404);
        }

        $generator = new PaySlipPdfGenerator();
        $pdfContent = $generator->generate($paySlip);

        $filename = sprintf('bulletin_%s_%s.pdf',
            $paySlip->employee_id,
            $paySlip->period_start->format('Y_m')
        );

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function sendSlips(Request $request, PayrollRun $payrollRun): JsonResponse
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
            return response()->json(['message' => 'Le run de paie doit être validé avant envoi.'], 422);
        }

        $slips = $payrollRun->paySlips()
            ->with('employee:id,first_name,last_name,email')
            ->whereIn('status', ['calculated', 'validated'])
            ->get();

        $sent = 0;
        foreach ($slips as $slip) {
            if (! empty($slip->employee->email)) {
                $slip->update(['status' => 'sent']);
                $sent++;
            }
        }

        return response()->json([
            'message' => "{$sent} bulletin(s) marqué(s) comme envoyé(s).",
            'sent_count' => $sent,
            'total_slips' => $slips->count(),
        ]);
    }
}
