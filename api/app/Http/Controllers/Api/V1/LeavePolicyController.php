<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeavePolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $policies = LeavePolicy::query()
            ->with('absenceType:id,name,code')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $policies]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'absence_type_id' => 'required|integer|exists:absence_types,id',
            'name' => 'required|string|max:150',
            'accrual_type' => 'required|in:monthly,yearly,manual',
            'accrual_amount' => 'required|numeric|min:0',
            'max_balance' => 'nullable|numeric|min:0',
            'carry_forward' => 'boolean',
            'carry_forward_max' => 'nullable|numeric|min:0',
            'carry_forward_expiry_days' => 'nullable|integer|min:0',
            'requires_approval' => 'boolean',
            'approval_levels' => 'nullable|integer|min:1|max:5',
            'min_notice_days' => 'nullable|integer|min:0',
            'max_consecutive_days' => 'nullable|integer|min:1',
            'applicable_roles' => 'nullable|array',
        ]);

        $policy = LeavePolicy::create([
            ...$validated,
            'company_id' => $actor->company_id,
        ]);

        return response()->json(['data' => $policy->load('absenceType:id,name,code')], 201);
    }

    public function show(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        if ($leavePolicy->company_id !== $request->user()->company_id) {
            abort(404);
        }

        return response()->json(['data' => $leavePolicy->load('absenceType:id,name,code')]);
    }

    public function update(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        $actor = $request->user();
        if ($leavePolicy->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'accrual_type' => 'sometimes|in:monthly,yearly,manual',
            'accrual_amount' => 'sometimes|numeric|min:0',
            'max_balance' => 'nullable|numeric|min:0',
            'carry_forward' => 'boolean',
            'carry_forward_max' => 'nullable|numeric|min:0',
            'carry_forward_expiry_days' => 'nullable|integer|min:0',
            'requires_approval' => 'boolean',
            'approval_levels' => 'nullable|integer|min:1|max:5',
            'min_notice_days' => 'nullable|integer|min:0',
            'max_consecutive_days' => 'nullable|integer|min:1',
            'applicable_roles' => 'nullable|array',
            'active' => 'boolean',
        ]);

        $leavePolicy->update($validated);

        return response()->json(['data' => $leavePolicy->fresh()->load('absenceType:id,name,code')]);
    }

    public function balances(Request $request): JsonResponse
    {
        $actor = $request->user();
        $year = $request->integer('year', (int) now()->format('Y'));

        $query = LeaveBalance::query()
            ->with(['absenceType:id,name,code', 'employee:id,first_name,last_name'])
            ->forYear($year);

        if (! $actor->isManager()) {
            $query->where('employee_id', $actor->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        return response()->json(['data' => $query->get()]);
    }
}
