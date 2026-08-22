<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LoanResource;
use App\Http\Resources\Api\V1\TrainingEnrollmentResource;
use App\Modules\HR\Domain\Models\CareerEvent;
use App\Modules\HR\Domain\Models\Contract;
use App\Modules\HR\Domain\Models\TrainingEnrollment;
use App\Modules\Payroll\Domain\Models\EmployeeLoan;
use App\Modules\Payroll\Domain\Models\LoanRepayment;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // Plans de carrière (issue #5259) : événements de l'employé — le
        // parcours est complet de bout en bout (contrats + événements).
        $careerEvents = CareerEvent::query()
            ->where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->with(['fromPosition:id,name', 'toPosition:id,name', 'fromDepartment:id,name', 'toDepartment:id,name'])
            ->orderByDesc('effective_date')
            ->get()
            ->map(fn (CareerEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'status' => $event->status,
                'from_position' => $event->fromPosition?->name,
                'to_position' => $event->toPosition?->name,
                'from_department' => $event->fromDepartment?->name,
                'to_department' => $event->toDepartment?->name,
                'from_salary' => $event->from_salary !== null ? (float) $event->from_salary : null,
                'to_salary' => $event->to_salary !== null ? (float) $event->to_salary : null,
                'effective_date' => $event->effective_date?->toDateString(),
                'reason' => $event->reason,
            ])
            ->values();

        return response()->json([
            'data' => [
                'available_for_new_company' => $user->status !== 'active' || $user->company_id === null,
                'current_company_id' => $user->company_id,
                'current_company_name' => $user->company?->name,
                'timeline' => $contracts,
                'career_events' => $careerEvents,
            ],
        ]);
    }

    public function myTrainings(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $enrollments = TrainingEnrollment::where('employee_id', $user->id)
            ->where('company_id', $user->company_id)
            ->with(['session.course:id,title,category,type,duration_hours'])
            ->orderByDesc('created_at')
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

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

        try {
            // #4978 : transaction imbriquée/savepoint — une violation unique
            // attendue est rollbackée localement, évitant l'état 25P02
            // (current transaction is aborted) qui contaminerait les requêtes
            // suivantes (notamment dans les tests de course).
            $enrollment = DB::transaction(fn (): TrainingEnrollment => TrainingEnrollment::create([
                'training_session_id' => $sessionId,
                'employee_id' => $user->id,
                'company_id' => $user->company_id,
                'status' => 'enrolled',
            ]));
        } catch (QueryException $e) {
            // Issue #3811 : course entre le exists() ci-dessus et le create()
            // (contrainte unique (training_session_id, employee_id)) — une
            // requête concurrente a gagné la course. 23505 = SQLSTATE
            // unique_violation (pattern PartnerService #3238) : réponse 422
            // idempotente, jamais de 500.
            if ($e->getCode() === '23505') {
                Log::warning("Training enrollment race for session {$sessionId}, employee {$user->id} — concurrent create won.");

                return response()->json(['message' => 'Already enrolled in this session.'], 422);
            }

            throw $e;
        }

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
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

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
