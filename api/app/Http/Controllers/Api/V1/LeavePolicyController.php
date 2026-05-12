<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Http\Controllers\Controller;
use App\Models\LeaveAccrual;
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
        /** @var Employee $actor */
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
        /** @var Employee $user */
        $user = $request->user();
        if ($leavePolicy->company_id !== $user->company_id) {
            abort(404);
        }

        return response()->json(['data' => $leavePolicy->load('absenceType:id,name,code')]);
    }

    public function update(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        /** @var Employee $actor */
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
        /** @var Employee $actor */
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

    public function destroy(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($leavePolicy->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $leavePolicy->update(['active' => false]);

        return response()->json(['message' => 'Policy deactivated.']);
    }

    public function myBalances(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $year = $request->integer('year', (int) now()->format('Y'));

        $balances = LeaveBalance::query()
            ->with('absenceType:id,name,code')
            ->where('employee_id', $actor->id)
            ->forYear($year)
            ->get();

        return response()->json(['data' => $balances]);
    }

    public function accruals(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $query = LeaveAccrual::query()
            ->with(['employee:id,first_name,last_name', 'leavePolicy:id,name'])
            ->orderByDesc('effective_date');

        if (! $actor->isManager()) {
            $query->where('employee_id', $actor->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        $perPage = $request->integer('per_page', 25);

        return response()->json($query->paginate($perPage));
    }

    public function storeAccrual(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'leave_policy_id' => 'required|integer|exists:leave_policies,id',
            'amount' => 'required|numeric',
            'type' => 'required|in:accrual,adjustment,carry_forward',
            'description' => 'nullable|string|max:255',
            'effective_date' => 'required|date',
        ]);

        $accrual = LeaveAccrual::create([
            ...$validated,
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]);

        $balance = LeaveBalance::firstOrCreate(
            [
                'company_id' => $actor->company_id,
                'employee_id' => $validated['employee_id'],
                'absence_type_id' => $accrual->leavePolicy->absence_type_id,
                'year' => (int) date('Y', strtotime($validated['effective_date'])),
            ],
            ['balance' => 0, 'used' => 0, 'pending' => 0]
        );

        $balance->increment('balance', (float) $validated['amount']);

        return response()->json(['data' => $accrual->load(['employee:id,first_name,last_name', 'leavePolicy:id,name'])], 201);
    }
}
