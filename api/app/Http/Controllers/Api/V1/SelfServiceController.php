<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanRepayment;
use App\Models\TrainingEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfServiceController extends Controller
{
    public function myTrainings(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $enrollments = TrainingEnrollment::where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->with(['trainingSession.trainingCourse:id,title,category,type,duration_hours'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $enrollments->items(),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    public function selfEnroll(Request $request, int $sessionId): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $exists = TrainingEnrollment::where('training_session_id', $sessionId)
            ->where('employee_id', $user->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already enrolled in this session.'], 422);
        }

        $enrollment = TrainingEnrollment::create([
            'training_session_id' => $sessionId,
            'employee_id' => $user->id,
            'company_id' => $user->company_id,
            'status' => 'enrolled',
        ]);

        return response()->json(['data' => $enrollment], 201);
    }

    public function myLoans(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $loans = EmployeeLoan::where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->withCount('repayments')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $loans->items(),
            'meta' => [
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
                'per_page' => $loans->perPage(),
                'total' => $loans->total(),
            ],
        ]);
    }

    public function myLoanRepayments(Request $request, int $loanId): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $loan = EmployeeLoan::where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->where('id', $loanId)
            ->firstOrFail();

        $repayments = LoanRepayment::where('employee_loan_id', $loan->id)
            ->orderBy('due_date')
            ->get();

        return response()->json(['data' => $repayments]);
    }
}
