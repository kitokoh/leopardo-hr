<?php

declare(strict_types=1);

namespace App\Modules\Planning\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LeaveAccrualResource;
use App\Http\Resources\Api\V1\LeaveBalanceResource;
use App\Http\Resources\Api\V1\LeavePolicyResource;
use App\Modules\Planning\Application\Actions\CreateLeaveAccrual;
use App\Modules\Planning\Application\Actions\CreateLeavePolicy;
use App\Modules\Planning\Application\Actions\DeactivateLeavePolicy;
use App\Modules\Planning\Application\Actions\UpdateLeavePolicy;
use App\Modules\Planning\Domain\Models\LeaveAccrual;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeavePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeavePolicyController extends Controller
{
    public function __construct(
        private readonly CreateLeavePolicy $createLeavePolicy,
        private readonly UpdateLeavePolicy $updateLeavePolicy,
        private readonly DeactivateLeavePolicy $deactivateLeavePolicy,
        private readonly CreateLeaveAccrual $createLeaveAccrual,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $policies = LeavePolicy::query()
            ->with('absenceType:id,name,code')
            ->where('company_id', $actor->company_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return LeavePolicyResource::collection($policies)->response();
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($actor->hasManagerRole('principal', 'rh') === false) {
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

        $policy = $this->createLeavePolicy->execute($actor, $validated);

        return (new LeavePolicyResource($policy->load('absenceType:id,name,code')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if ($leavePolicy->company_id !== $user->company_id) {
            abort(404);
        }

        return (new LeavePolicyResource($leavePolicy->load('absenceType:id,name,code')))->response();
    }

    public function update(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($leavePolicy->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->hasManagerRole('principal', 'rh') === false) {
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

        $leavePolicy = $this->updateLeavePolicy->execute($leavePolicy, $validated);

        return (new LeavePolicyResource($leavePolicy->load('absenceType:id,name,code')))->response();
    }

    public function balances(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $year = $request->integer('year', (int) now()->format('Y'));

        $query = LeaveBalance::query()
            ->with(['absenceType:id,name,code', 'employee:id,first_name,last_name'])
            ->where('company_id', $actor->company_id)
            ->forYear($year);

        if ($actor->isManager() === false) {
            $query->where('employee_id', $actor->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        return LeaveBalanceResource::collection($query->get())->response();
    }

    public function destroy(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($leavePolicy->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }

        $this->deactivateLeavePolicy->execute($leavePolicy);

        return response()->json(['message' => 'Policy deactivated.']);
    }

    public function myBalances(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $year = $request->integer('year', (int) now()->format('Y'));

        $balances = LeaveBalance::query()
            ->with('absenceType:id,name,code')
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->forYear($year)
            ->get();

        return LeaveBalanceResource::collection($balances)->response();
    }

    public function accruals(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $query = LeaveAccrual::query()
            ->with(['employee:id,first_name,last_name', 'leavePolicy:id,name'])
            ->where('company_id', $actor->company_id)
            ->orderByDesc('effective_date');

        if ($actor->isManager() === false) {
            $query->where('employee_id', $actor->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        $perPage = max(1, min(100, $request->integer('per_page', 25)));

        return LeaveAccrualResource::collection($query->paginate($perPage))->response();
    }

    public function storeAccrual(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($actor->hasManagerRole('principal', 'rh') === false) {
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

        $accrual = $this->createLeaveAccrual->execute($actor, $validated);

        return (new LeaveAccrualResource($accrual->load(['employee:id,first_name,last_name', 'leavePolicy:id,name'])))
            ->response()
            ->setStatusCode(201);
    }
}
