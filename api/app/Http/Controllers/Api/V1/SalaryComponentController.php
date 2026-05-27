<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SalaryComponentResource;
use App\Models\Employee;
use App\Models\SalaryComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\Payroll\StoreSalaryComponentRequest;
use App\Http\Requests\Api\V1\Payroll\UpdateSalaryComponentRequest;

class SalaryComponentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $query = SalaryComponent::where('company_id', $actor->company_id);

        if ($request->filled('salary_structure_id')) {
            $query->where('salary_structure_id', $request->integer('salary_structure_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        return SalaryComponentResource::collection($query->orderBy('order')->get())->response();
    }

    public function store(StoreSalaryComponentRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

        $component = SalaryComponent::create([
            'company_id' => $actor->company_id,
            'salary_structure_id' => $validated['salary_structure_id'] ?? null,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'type' => $validated['type'],
            'calculation_type' => $validated['calculation_type'],
            'amount' => $validated['amount'] ?? null,
            'percentage' => $validated['percentage'] ?? null,
            'formula' => $validated['formula'] ?? null,
            'is_taxable' => $validated['is_taxable'] ?? true,
            'is_recurring' => $validated['is_recurring'] ?? true,
            'order' => $validated['order'] ?? 0,
            'active' => true,
        ]);

        return (new SalaryComponentResource($component))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, SalaryComponent $salaryComponent): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryComponent->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        return (new SalaryComponentResource($salaryComponent))->response();
    }

    public function update(UpdateSalaryComponentRequest $request, SalaryComponent $salaryComponent): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryComponent->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

        $salaryComponent->update($validated);

        return (new SalaryComponentResource($salaryComponent->refresh()))->response();
    }

    public function destroy(Request $request, SalaryComponent $salaryComponent): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryComponent->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $salaryComponent->delete();

        return response()->json(['message' => 'Salary component deleted successfully.']);
    }
}
