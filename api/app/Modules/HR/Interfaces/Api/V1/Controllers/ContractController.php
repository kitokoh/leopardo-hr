<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContractAmendmentResource;
use App\Http\Resources\Api\V1\ContractResource;
use App\Modules\HR\Application\Actions\ContractLifecycleAction;
use App\Modules\HR\Domain\Contracts\ContractDocumentGeneratorInterface;
use App\Modules\HR\Domain\Exceptions\InvalidContractTransitionException;
use App\Modules\HR\Domain\Models\Contract;
use App\Modules\HR\Domain\Models\ContractAmendment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContractController extends Controller
{
    public function __construct(private readonly ContractLifecycleAction $contractLifecycle) {}

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

        $perPage = max(1, min(100, $request->integer('per_page', 15)));

        return ContractResource::collection($query->orderByDesc('start_date')->paginate($perPage))
            ->response();
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($actor->hasManagerRole('principal', 'rh') === false) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('company_id', $actor->company_id)],
            'contract_type' => 'required|in:cdi,cdd,stage,freelance,interim',
            'reference' => 'nullable|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'job_title' => 'nullable|string|max:150',
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('company_id', $actor->company_id)],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('company_id', $actor->company_id)],
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
        if ($actor->isManager() === false && (string) $contract->employee_id !== (string) $actor->id) {
            abort(403);
        }

        return (new ContractResource($contract->load(['employee:id,first_name,last_name', 'department:id,name', 'position:id,name', 'amendments'])))
            ->response();
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
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('company_id', $actor->company_id)],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('company_id', $actor->company_id)],
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
        if ($actor->isManager() === false && (string) $contract->employee_id !== (string) $actor->id) {
            abort(403);
        }

        return ContractAmendmentResource::collection($contract->amendments()->orderByDesc('effective_date')->get())
            ->response();
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
        $this->authorize('activate', $contract);

        try {
            $contractFresh = $this->contractLifecycle->activate($contract);
        } catch (InvalidContractTransitionException $e) {
            // #4314 : code stable + message localisé, jamais le message brut EN.
            return response()->json([
                'error' => 'CONTRACT_ACTIVATION_INVALID_STATE',
                'message' => 'CONTRACT_ACTIVATION_INVALID_STATE',
                'localized_message' => __('errors.CONTRACT_ACTIVATION_INVALID_STATE'),
            ], 422);
        }

        return (new ContractResource($contractFresh->load('employee:id,first_name,last_name')))->response();
    }

    public function suspend(Request $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        $this->authorize('activate', $contract);

        try {
            $contractFresh = $this->contractLifecycle->suspend($contract);
        } catch (InvalidContractTransitionException $e) {
            // #4314 : code stable + message localisé.
            return response()->json([
                'error' => 'CONTRACT_SUSPENSION_INVALID_STATE',
                'message' => 'CONTRACT_SUSPENSION_INVALID_STATE',
                'localized_message' => __('errors.CONTRACT_SUSPENSION_INVALID_STATE'),
            ], 422);
        }

        return (new ContractResource($contractFresh->load('employee:id,first_name,last_name')))->response();
    }

    public function terminate(Request $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        $this->authorize('terminate', $contract);

        $validated = $request->validate([
            'termination_reason' => 'required|string|max:500',
        ]);

        try {
            $contractFresh = $this->contractLifecycle->terminate($contract, $validated['termination_reason']);
        } catch (InvalidContractTransitionException $e) {
            // #4314 : code stable + message localisé.
            return response()->json([
                'error' => 'CONTRACT_TERMINATION_INVALID_STATE',
                'message' => 'CONTRACT_TERMINATION_INVALID_STATE',
                'localized_message' => __('errors.CONTRACT_TERMINATION_INVALID_STATE'),
            ], 422);
        }

        return (new ContractResource($contractFresh->load('employee:id,first_name,last_name')))->response();
    }

    public function renew(Request $request, Contract $contract): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        $this->authorize('renew', $contract);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'base_salary' => 'nullable|numeric|min:0',
        ]);

        $newContract = $this->contractLifecycle->renew($contract, $actor, $validated);

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

    /**
     * Return the authenticated employee's active contract, i.e. the one
     * whose period covers today (status active/suspended, start_date <= now
     * and end_date null or >= now). Falls back to the most recently started
     * contract when none is currently active, so the endpoint still returns
     * something useful for employees between contracts.
     */
    public function myActiveContract(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $baseQuery = Contract::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id)
            ->with(['department:id,name', 'position:id,name']);

        $today = now()->toDateString();

        $contract = (clone $baseQuery)
            ->whereIn('status', ['active', 'suspended'])
            ->where('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->orderByDesc('start_date')
            ->first();

        if ($contract === null) {
            $contract = (clone $baseQuery)
                ->orderByDesc('start_date')
                ->first();
        }

        if ($contract === null) {
            return response()->json(['message' => 'No contract found for this employee.'], 404);
        }

        return (new ContractResource($contract))->response();
    }

    public function generatePdf(Request $request, Contract $contract, ContractDocumentGeneratorInterface $generator)
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($contract->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->isManager() === false && (string) $contract->employee_id !== (string) $actor->id) {
            abort(403);
        }

        $pdfContent = $generator->generate($contract);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contract_'.$contract->id.'.pdf"',
        ]);
    }
}
