<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SalaryAdvance\DecideSalaryAdvanceRequest;
use App\Http\Requests\Api\V1\SalaryAdvance\SalaryAdvanceIndexRequest;
use App\Http\Requests\Api\V1\SalaryAdvance\StoreSalaryAdvanceRequest;
use App\Http\Resources\Api\V1\SalaryAdvanceResource;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use App\Services\SalaryAdvanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryAdvanceController extends Controller
{
    public function __construct(private readonly SalaryAdvanceService $salaryAdvanceService) {}

    public function index(SalaryAdvanceIndexRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $query = SalaryAdvance::query()
            ->with([
                'employee:id,first_name,last_name,email,company_id',
            ]);

        if (! $actor->isManager()) {
            $query->where('employee_id', $actor->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->integer('per_page', 15);

        return SalaryAdvanceResource::collection($query->orderByDesc('created_at')->paginate($perPage))
            ->response();
    }

    public function store(StoreSalaryAdvanceRequest $request): JsonResponse
    {
        $advance = $this->salaryAdvanceService->create($request->user(), $request->validated());

        return (new SalaryAdvanceResource($advance->load('employee:id,first_name,last_name,email,company_id')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $salaryAdvance->employee_id !== $actor->id) {
            abort(403);
        }

        return (new SalaryAdvanceResource($salaryAdvance->load('employee:id,first_name,last_name,email,company_id')))->response();
    }

    public function approve(DecideSalaryAdvanceRequest $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $advance = $this->salaryAdvanceService->approve($salaryAdvance, $actor, $request->validated());

        return (new SalaryAdvanceResource($advance->load('employee:id,first_name,last_name,email,company_id')))->response();
    }

    public function reject(DecideSalaryAdvanceRequest $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $advance = $this->salaryAdvanceService->reject($salaryAdvance, $actor, $request->validated('decision_comment'));

        return (new SalaryAdvanceResource($advance->load('employee:id,first_name,last_name,email,company_id')))->response();
    }

    public function destroy(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($salaryAdvance->employee_id !== $actor->id) {
            abort(403);
        }

        $advance = $this->salaryAdvanceService->cancel($salaryAdvance);

        return (new SalaryAdvanceResource($advance->load('employee:id,first_name,last_name,email,company_id')))->response();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Plan 60 — Double validation workflow
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * PUT /salary-advances/{id}/mark-paid
     * Manager (principal | comptable | rh) marks an advance as paid.
     */
    public function markPaid(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403, 'Manager role required.');
        }
        if ($salaryAdvance->validation_status !== 'manager_approved') {
            return response()->json(['message' => 'Advance must be manager-approved before declaring payment.'], 422);
        }

        $validated = $request->validate([
            'payment_reference' => 'nullable|string|max:255',
            'payment_note' => 'nullable|string|max:1000',
        ]);

        $salaryAdvance->update([
            'payment_declared_at' => now(),
            'payment_declared_by' => $actor->id,
            'payment_reference' => $validated['payment_reference'] ?? null,
            'payment_note' => $validated['payment_note'] ?? null,
            'validation_status' => 'payment_declared',
            'status' => 'active', // keep existing status flow
        ]);

        return (new SalaryAdvanceResource($salaryAdvance->fresh()->load('employee:id,first_name,last_name,email,company_id')))->response();
    }

    /**
     * PUT /salary-advances/{id}/confirm-received
     * Employee confirms they received the advance.
     */
    public function confirmReceived(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($salaryAdvance->employee_id !== $actor->id) {
            abort(403, 'Only the advance owner can confirm reception.');
        }
        if ($salaryAdvance->validation_status !== 'payment_declared') {
            return response()->json(['message' => 'Payment must be declared before employee confirmation.'], 422);
        }

        $salaryAdvance->update([
            'employee_confirmed_at' => now(),
            'validation_status' => 'employee_confirmed',
        ]);

        return (new SalaryAdvanceResource($salaryAdvance->fresh()->load('employee:id,first_name,last_name,email,company_id')))->response();
    }

    /**
     * Alias kept for backward compatibility with Plan 60 naming.
     * PUT /salary-advances/{id}/manager-approve
     * Sets validation_status to manager_approved after existing approve flow.
     */
    public function managerApprove(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403, 'Manager role required.');
        }
        if (! in_array($salaryAdvance->status, ['pending', 'approved'], true)) {
            return response()->json(['message' => 'Only pending advances can be manager-approved.'], 422);
        }

        $salaryAdvance->update([
            'manager_approved_at' => now(),
            'manager_approved_by' => $actor->id,
            'validation_status' => 'manager_approved',
            'status' => 'approved',
        ]);

        return (new SalaryAdvanceResource($salaryAdvance->fresh()->load('employee:id,first_name,last_name,email,company_id')))->response();
    }
}
