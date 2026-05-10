<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractAmendment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = Contract::query()->with(['employee:id,first_name,last_name', 'department:id,name', 'position:id,name']);

        if (! $actor->isManager()) {
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
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
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
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $contract->employee_id !== $actor->id) {
            abort(403);
        }

        return response()->json(['data' => $contract->load(['employee:id,first_name,last_name', 'department:id,name', 'position:id,name', 'amendments'])]);
    }

    public function update(Request $request, Contract $contract): JsonResponse
    {
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
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

        return response()->json(['data' => $contract->fresh()->load(['employee:id,first_name,last_name', 'department:id,name'])]);
    }

    public function amendments(Request $request, Contract $contract): JsonResponse
    {
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }

        return response()->json(['data' => $contract->amendments()->orderByDesc('effective_date')->get()]);
    }

    public function storeAmendment(Request $request, Contract $contract): JsonResponse
    {
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
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
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $days = $request->integer('days', 30);
        $contracts = Contract::expiringSoon($days)
            ->with('employee:id,first_name,last_name')
            ->get();

        return response()->json(['data' => $contracts]);
    }
}
