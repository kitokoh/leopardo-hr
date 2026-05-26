<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LoanResource;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanRepayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeLoanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $query = EmployeeLoan::query()
            ->where('company_id', $actor->company_id)
            ->with('employee:id,first_name,last_name');

        if (! $actor->isManager()) {
            $query->where('employee_id', $actor->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return LoanResource::collection($query->orderByDesc('created_at')->paginate($request->integer('per_page', 15)))
            ->response();
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'employee_id' => $actor->isManager()
                ? [
                    'required',
                    'integer',
                    Rule::exists('employees', 'id')->where('company_id', $actor->company_id),
                ]
                : 'prohibited',
            'loan_type' => 'required|in:personal,housing,vehicle,education,emergency',
            'amount' => 'required|numeric|min:1',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'installments' => 'required|integer|min:1|max:120',
            'start_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $employeeId = $validated['employee_id'] ?? $actor->id;
        $interestRate = $validated['interest_rate'] ?? 0;
        $installmentAmount = round($validated['amount'] * (1 + $interestRate / 100) / $validated['installments'], 2);

        $loan = DB::transaction(function () use ($actor, $validated, $employeeId, $interestRate, $installmentAmount) {
            $loan = EmployeeLoan::create([
                'company_id' => $actor->company_id,
                'employee_id' => $employeeId,
                'loan_type' => $validated['loan_type'],
                'amount' => $validated['amount'],
                'interest_rate' => $interestRate,
                'installments' => $validated['installments'],
                'installment_amount' => $installmentAmount,
                'start_date' => $validated['start_date'],
                'status' => $actor->isManager() ? 'pending_approval' : 'draft',
                'notes' => $validated['notes'] ?? null,
            ]);

            $startDate = Carbon::parse($validated['start_date']);
            $totalInterest = $validated['amount'] * ($interestRate / 100);
            $interestPerInstallment = $validated['installments'] > 0 ? round($totalInterest / $validated['installments'], 2) : 0;
            $principalPerInstallment = round($validated['amount'] / $validated['installments'], 2);

            for ($i = 0; $i < $validated['installments']; $i++) {
                LoanRepayment::create([
                    'employee_loan_id' => $loan->id,
                    'company_id' => $actor->company_id,
                    'due_date' => $startDate->copy()->addMonths($i + 1)->toDateString(),
                    'amount' => $installmentAmount,
                    'principal' => $principalPerInstallment,
                    'interest' => $interestPerInstallment,
                    'status' => 'pending',
                ]);
            }

            return $loan;
        });

        return (new LoanResource($loan->load('repayments')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, EmployeeLoan $employeeLoan): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($employeeLoan->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $employeeLoan->employee_id !== $actor->id) {
            abort(403);
        }

        return (new LoanResource($employeeLoan->load(['employee:id,first_name,last_name', 'repayments'])))->response();
    }

    public function approve(Request $request, EmployeeLoan $employeeLoan): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($employeeLoan->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }
        if (in_array($employeeLoan->status, ['draft', 'pending_approval'], true) === false) {
            abort(422, 'Loan is not in approvable state.');
        }

        $employeeLoan->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
        ]);

        return (new LoanResource($employeeLoan->fresh()))->response();
    }

    public function disburse(Request $request, EmployeeLoan $employeeLoan): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($employeeLoan->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }
        if ($employeeLoan->status !== 'approved') {
            abort(422, 'Loan must be approved first.');
        }

        $employeeLoan->update([
            'status' => 'disbursed',
            'disbursed_at' => now(),
        ]);

        return (new LoanResource($employeeLoan->fresh()))->response();
    }
}
