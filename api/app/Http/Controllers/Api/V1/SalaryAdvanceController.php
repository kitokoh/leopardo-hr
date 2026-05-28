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
}
