<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LeaveAccrualResource;
use App\Http\Resources\Api\V1\LeaveBalanceResource;
use App\Http\Resources\Api\V1\LeavePolicyResource;
use App\Models\AbsenceType;
use App\Models\Employee;
use App\Models\LeaveAccrual;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Api\V1\LeavePolicy\StoreAccrualLeavePolicyRequest;
use App\Http\Requests\Api\V1\LeavePolicy\StoreLeavePolicyRequest;
use App\Http\Requests\Api\V1\LeavePolicy\UpdateLeavePolicyRequest;

class LeavePolicyController extends Controller
{
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

    public function store(StoreLeavePolicyRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }

        $validated = $request->validated();

        $absenceTypeBelongsToCompany = AbsenceType::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($validated['absence_type_id'])
            ->exists();

        if ($absenceTypeBelongsToCompany === false) {
            throw ValidationException::withMessages([
                'absence_type_id' => ['The selected absence type is invalid.'],
            ]);
        }

        $policy = LeavePolicy::create([
            ...$validated,
            'company_id' => $actor->company_id,
        ]);

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

    public function update(UpdateLeavePolicyRequest $request, LeavePolicy $leavePolicy): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($leavePolicy->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }

        $validated = $request->validated();

        $leavePolicy->update($validated);

        /** @var LeavePolicy $leavePolicyFresh */
        $leavePolicyFresh = $leavePolicy->fresh();

        return (new LeavePolicyResource($leavePolicyFresh->load('absenceType:id,name,code')))->response();
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

        $perPage = $request->integer('per_page', 25);

        return LeaveAccrualResource::collection($query->paginate($perPage))->response();
    }

    public function storeAccrual(StoreAccrualLeavePolicyRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }

        $validated = $request->validated();

        $employeeBelongsToCompany = Employee::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($validated['employee_id'])
            ->exists();
        $policy = LeavePolicy::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($validated['leave_policy_id'])
            ->first();

        if ($employeeBelongsToCompany === false) {
            throw ValidationException::withMessages([
                'employee_id' => ['The selected employee is invalid.'],
            ]);
        }

        if ($policy === null) {
            throw ValidationException::withMessages([
                'leave_policy_id' => ['The selected leave policy is invalid.'],
            ]);
        }

        $accrual = LeaveAccrual::create([
            ...$validated,
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
        ]);

        $balance = LeaveBalance::firstOrCreate(
            [
                'company_id' => $actor->company_id,
                'employee_id' => $validated['employee_id'],
                'absence_type_id' => $policy->absence_type_id,
                'year' => (int) date('Y', strtotime($validated['effective_date'])),
            ],
            ['balance' => 0, 'used' => 0, 'pending' => 0]
        );

        $balance->increment('balance', (float) $validated['amount']);

        return (new LeaveAccrualResource($accrual->load(['employee:id,first_name,last_name', 'leavePolicy:id,name'])))
            ->response()
            ->setStatusCode(201);
    }
}
