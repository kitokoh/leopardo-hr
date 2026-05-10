<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaySlipController extends Controller
{
    public function indexForRun(Request $request, PayrollRun $payrollRun): JsonResponse
    {
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
}
