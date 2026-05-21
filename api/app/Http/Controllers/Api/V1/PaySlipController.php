<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Services\PaySlipPdfGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PaySlipController extends Controller
{
    /**
     * Liste paginee des bulletins du tenant courant (manager).
     * Evite le pattern N+1 "un GET pay-slips par run" cote SPA.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'payroll_run_id' => 'sometimes|nullable|integer',
            'status' => 'sometimes|nullable|string|in:calculated,validated,sent',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'sort_by' => 'sometimes|nullable|string|in:period_start,period_end,net_salary,status,id',
            'sort_dir' => 'sometimes|nullable|string|in:asc,desc',
        ]);

        $query = PaySlip::query()
            ->where('company_id', $actor->company_id)
            ->with(['employee:id,first_name,last_name,email']);

        if (! empty($validated['payroll_run_id'])) {
            $runId = (int) $validated['payroll_run_id'];
            $belongs = PayrollRun::query()
                ->whereKey($runId)
                ->where('company_id', $actor->company_id)
                ->exists();
            if (! $belongs) {
                abort(404);
            }
            $query->where('payroll_run_id', $runId);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $perPage = (int) ($validated['per_page'] ?? 50);
        $sortBy = (string) ($validated['sort_by'] ?? 'period_start');
        $sortDir = (string) ($validated['sort_dir'] ?? 'desc');

        $slips = $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate($perPage);

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

        $validated = $request->validate([
            'status' => 'sometimes|nullable|string|in:validated,sent',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'sort_by' => 'sometimes|nullable|string|in:period_start,period_end,net_salary,status,id',
            'sort_dir' => 'sometimes|nullable|string|in:asc,desc',
        ]);

        $statuses = ! empty($validated['status'])
            ? [(string) $validated['status']]
            : ['validated', 'sent'];
        $sortBy = (string) ($validated['sort_by'] ?? 'period_start');
        $sortDir = (string) ($validated['sort_dir'] ?? 'desc');

        $slips = PaySlip::where('employee_id', $actor->id)
            ->where('company_id', $actor->company_id)
            ->whereIn('status', $statuses)
            ->with('payrollRun:id,period_start,period_end,country_code')
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 12));

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

    public function downloadPdf(Request $request, PaySlip $paySlip, PaySlipPdfGenerator $generator): Response
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $isOwner = $paySlip->employee_id === $actor->id && $paySlip->company_id === $actor->company_id;
        $isManager = $paySlip->company_id === $actor->company_id && $actor->isManager();

        if (! $isOwner && ! $isManager) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if ($paySlip->pdf_path !== null && $disk->exists($paySlip->pdf_path)) {
            $pdfContent = $disk->get($paySlip->pdf_path);
        } else {
            $pdfContent = $generator->generate($paySlip);
        }

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
