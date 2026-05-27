<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContractAmendmentResource;
use App\Http\Resources\Api\V1\ContractResource;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Api\V1\Contract\RenewContractRequest;
use App\Http\Requests\Api\V1\Contract\StoreAmendmentContractRequest;
use App\Http\Requests\Api\V1\Contract\StoreContractRequest;
use App\Http\Requests\Api\V1\Contract\TerminateContractRequest;
use App\Http\Requests\Api\V1\Contract\UpdateContractRequest;

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

        return ContractResource::collection($query->orderByDesc('start_date')->paginate($perPage))
            ->response();
    }

    public function store(StoreContractRequest $request): JsonResponse
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

        return (new ContractResource($contract->load(['employee:id,first_name,last_name', 'department:id,name'])))
            ->response()
            ->setStatusCode(201);
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

        return (new ContractResource($contract->load(['employee:id,first_name,last_name', 'department:id,name', 'position:id,name', 'amendments'])))
            ->response();
    }

    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }

        $validated = $request->validated();

        if (isset($validated['status']) && $validated['status'] === 'terminated') {
            $validated['terminated_at'] = now();
        }
        if (isset($validated['status']) && $validated['status'] === 'active' && $contract->status === 'draft') {
            $validated['signed_at'] = now();
        }

        $contract->update($validated);

        /** @var Contract $contractFresh */
        $contractFresh = $contract->fresh();

        return (new ContractResource($contractFresh->load(['employee:id,first_name,last_name', 'department:id,name'])))
            ->response();
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

        return ContractAmendmentResource::collection($contract->amendments()->orderByDesc('effective_date')->get())
            ->response();
    }

    public function storeAmendment(StoreAmendmentContractRequest $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }

        $validated = $request->validated();

        $amendment = ContractAmendment::create([
            'contract_id' => $contract->id,
            'company_id' => $actor->company_id,
            'approved_by' => $actor->id,
            ...$validated,
        ]);

        return (new ContractAmendmentResource($amendment))
            ->response()
            ->setStatusCode(201);
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

        return ContractResource::collection($contracts)->response();
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

        return (new ContractResource($contractFresh->load('employee:id,first_name,last_name')))->response();
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

        return (new ContractResource($contractFresh->load('employee:id,first_name,last_name')))->response();
    }

    public function terminate(TerminateContractRequest $request, Contract $contract): JsonResponse
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

        $validated = $request->validated();

        $contract->update([
            'status' => 'terminated',
            'termination_reason' => $validated['termination_reason'],
            'terminated_at' => now(),
        ]);

        /** @var Contract $contractFresh */
        $contractFresh = $contract->fresh();

        return (new ContractResource($contractFresh->load('employee:id,first_name,last_name')))->response();
    }

    public function renew(RenewContractRequest $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }

        $validated = $request->validated();

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

        return ContractResource::collection($contracts)->response();
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
