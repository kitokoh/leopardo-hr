<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContractResource;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $query = Contract::query()
            ->with(['employee:id,first_name,last_name', 'department:id,name', 'position:id,name'])
            ->where('company_id', $actor->company_id);

        if ($actor->isManager() === false) {
            $query->where('employee_id', $actor->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->integer('per_page', 15);

        return response()->json($query->orderByDesc('start_date')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'contract_type' => 'required|in:cdi,cdd,stage,freelance,interim',
            'reference' => 'nullable|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'job_title' => 'nullable|string|max:150',
            'department_id' => 'nullable|integer|exists:departments,id',
            'position_id' => 'nullable|integer|exists:positions,id',
            'base_salary' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'salary_frequency' => 'nullable|in:monthly,hourly,daily',
            'work_hours_per_week' => 'nullable|numeric|min:0|max:168',
            'probation_end_date' => 'nullable|date|after:start_date',
            'benefits' => 'nullable|array',
            'clauses' => 'nullable|array',
        ]);

        $employeeBelongsToCompany = Employee::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($validated['employee_id'])
            ->exists();

        if ($employeeBelongsToCompany === false) {
            throw ValidationException::withMessages([
                'employee_id' => ['The selected employee is invalid.'],
            ]);
        }

        $contract = Contract::create([
            ...$validated,
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
            'status' => 'draft',
        ]);

        return response()->json(['data' => $contract->load(['employee:id,first_name,last_name', 'department:id,name'])], 201);
    }

    public function show(Request $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false && $contract->employee_id !== $actor->id) {
            abort(403);
        }

        return response()->json(['data' => $contract->load(['employee:id,first_name,last_name', 'department:id,name', 'position:id,name', 'amendments'])]);
    }

    public function update(Request $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }

        $validated = $request->validate([
            'contract_type' => 'sometimes|in:cdi,cdd,stage,freelance,interim',
            'reference' => 'nullable|string|max:50',
            'end_date' => 'nullable|date',
            'job_title' => 'nullable|string|max:150',
            'department_id' => 'nullable|integer|exists:departments,id',
            'position_id' => 'nullable|integer|exists:positions,id',
            'base_salary' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'salary_frequency' => 'nullable|in:monthly,hourly,daily',
            'work_hours_per_week' => 'nullable|numeric|min:0|max:168',
            'probation_end_date' => 'nullable|date',
            'benefits' => 'nullable|array',
            'clauses' => 'nullable|array',
            'status' => 'sometimes|in:draft,active,suspended,terminated',
            'termination_reason' => 'nullable|string',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'terminated') {
            $validated['terminated_at'] = now();
        }
        if (isset($validated['status']) && $validated['status'] === 'active' && $contract->status === 'draft') {
            $validated['signed_at'] = now();
        }

        $contract->update($validated);

        /** @var Contract $contractFresh */
        $contractFresh = $contract->fresh();

        return response()->json(['data' => $contractFresh->load(['employee:id,first_name,last_name', 'department:id,name'])]);
    }

    public function amendments(Request $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false && $contract->employee_id !== $actor->id) {
            abort(403);
        }

        return response()->json(['data' => $contract->amendments()->orderByDesc('effective_date')->get()]);
    }

    public function storeAmendment(Request $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }

        $validated = $request->validate([
            'amendment_type' => 'required|in:salary_change,position_change,hours_change,renewal,other',
            'changes' => 'required|array',
            'effective_date' => 'required|date',
            'reason' => 'nullable|string',
        ]);

        $amendment = ContractAmendment::create([
            'contract_id' => $contract->id,
            'company_id' => $actor->company_id,
            'approved_by' => $actor->id,
            ...$validated,
        ]);

        return response()->json(['data' => $amendment], 201);
    }

    public function expiring(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($actor->isManager() === false) {
            abort(403);
        }

        $days = $request->integer('days', 30);
        $contracts = Contract::expiringSoon($days)
            ->where('company_id', $actor->company_id)
            ->with('employee:id,first_name,last_name')
            ->get();

        return response()->json(['data' => $contracts]);
    }

    public function activate(Request $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }
        if ($contract->status !== 'draft') {
            return response()->json(['message' => 'Only draft contracts can be activated.'], 422);
        }

        $contract->update(['status' => 'active', 'signed_at' => now()]);

        /** @var Contract $contractFresh */
        $contractFresh = $contract->fresh();

        return response()->json(['data' => $contractFresh->load('employee:id,first_name,last_name')]);
    }

    public function suspend(Request $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }
        if ($contract->status !== 'active') {
            return response()->json(['message' => 'Only active contracts can be suspended.'], 422);
        }

        $contract->update(['status' => 'suspended']);

        /** @var Contract $contractFresh */
        $contractFresh = $contract->fresh();

        return response()->json(['data' => $contractFresh->load('employee:id,first_name,last_name')]);
    }

    public function terminate(Request $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }
        if (in_array($contract->status, ['active', 'suspended'], true) === false) {
            return response()->json(['message' => 'Contract must be active or suspended to terminate.'], 422);
        }

        $validated = $request->validate([
            'termination_reason' => 'required|string|max:500',
        ]);

        $contract->update([
            'status' => 'terminated',
            'termination_reason' => $validated['termination_reason'],
            'terminated_at' => now(),
        ]);

        /** @var Contract $contractFresh */
        $contractFresh = $contract->fresh();

        return response()->json(['data' => $contractFresh->load('employee:id,first_name,last_name')]);
    }

    public function renew(Request $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'base_salary' => 'nullable|numeric|min:0',
        ]);

        $newContract = DB::transaction(function () use ($contract, $validated, $actor) {
            $newContract = Contract::create([
                'company_id' => $contract->company_id,
                'employee_id' => $contract->employee_id,
                'contract_type' => $contract->contract_type,
                'reference' => null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'job_title' => $contract->job_title,
                'department_id' => $contract->department_id,
                'position_id' => $contract->position_id,
                'base_salary' => $validated['base_salary'] ?? $contract->base_salary,
                'currency' => $contract->currency,
                'salary_frequency' => $contract->salary_frequency,
                'work_hours_per_week' => $contract->work_hours_per_week,
                'benefits' => $contract->benefits,
                'clauses' => $contract->clauses,
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);

            if (in_array($contract->status, ['active', 'suspended'], true)) {
                $contract->update(['status' => 'expired']);
            }

            return $newContract;
        });

        return (new ContractResource($newContract->load('employee:id,first_name,last_name')))
            ->response()
            ->setStatusCode(201);
    }

    public function myContracts(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $contracts = Contract::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->with(['department:id,name', 'position:id,name'])
            ->orderByDesc('start_date')
            ->get();

        return response()->json(['data' => $contracts]);
    }

    public function generatePdf(Request $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false && $contract->employee_id !== $actor->id) {
            abort(403);
        }

        $data = $contract->load(['employee:id,first_name,last_name,email', 'department:id,name', 'position:id,name', 'amendments'])->toArray();

        return response()->json([
            'data' => [
                'contract' => $data,
                'generated_at' => now()->toIso8601String(),
                'message' => 'PDF data ready. Integrate with DomPDF/Snappy for file generation.',
            ],
        ]);
    }
}
