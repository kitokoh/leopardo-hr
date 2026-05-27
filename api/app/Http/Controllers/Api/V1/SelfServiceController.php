<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LoanResource;
use App\Http\Resources\Api\V1\TrainingEnrollmentResource;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanRepayment;
use App\Models\TrainingEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfServiceController extends Controller
{
    public function myCareer(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $contracts = Contract::query()
            ->where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Contract $contract): array => [
                'company_id' => $contract->company_id,
                'company_name' => $user->company?->name,
                'start_date' => $contract->start_date?->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
                'job_title' => $contract->job_title,
                'contract_type' => $contract->contract_type,
                'status' => $contract->status,
                'current' => $contract->status === 'active' && $contract->end_date === null,
            ])
            ->values();

        if ($contracts->isEmpty()) {
            $contracts->push([
                'company_id' => $user->company_id,
                'company_name' => $user->company?->name,
                'start_date' => $user->contract_start?->toDateString(),
                'end_date' => $user->contract_end?->toDateString(),
                'job_title' => data_get($user->extra_data, 'job_title'),
                'contract_type' => $user->contract_type,
                'status' => $user->status,
                'current' => $user->status === 'active' && $user->contract_end === null,
            ]);
        }

        return response()->json([
            'data' => [
                'available_for_new_company' => $user->status !== 'active' || $user->company_id === null,
                'current_company_id' => $user->company_id,
                'current_company_name' => $user->company?->name,
                'timeline' => $contracts,
            ],
        ]);
    }

    public function myTrainings(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $enrollments = TrainingEnrollment::where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->with(['trainingSession.trainingCourse:id,title,category,type,duration_hours'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return TrainingEnrollmentResource::collection($enrollments)->response();
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

        return (new TrainingEnrollmentResource($enrollment))
            ->response()
            ->setStatusCode(201);
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

        return LoanResource::collection($loans)->response();
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

        return response()->json(['data' => $repayments]); // LoanRepayment — no dedicated Resource yet
    }
}
