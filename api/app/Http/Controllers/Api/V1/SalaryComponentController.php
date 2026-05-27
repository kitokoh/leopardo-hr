<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SalaryComponentResource;
use App\Models\Employee;
use App\Models\SalaryComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'salary_structure_id' => 'nullable|integer|exists:salary_structures,id',
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50',
            'type' => 'required|in:earning,deduction,employer_contribution',
            'calculation_type' => 'required|in:fixed,percentage_of_base,percentage_of_gross,formula',
            'amount' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'formula' => 'nullable|string|max:500',
            'is_taxable' => 'nullable|boolean',
            'is_recurring' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
        ]);

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

    public function update(Request $request, SalaryComponent $salaryComponent): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryComponent->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'code' => 'sometimes|string|max:50',
            'type' => 'sometimes|in:earning,deduction,employer_contribution',
            'calculation_type' => 'sometimes|in:fixed,percentage_of_base,percentage_of_gross,formula',
            'amount' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'formula' => 'nullable|string|max:500',
            'is_taxable' => 'sometimes|boolean',
            'is_recurring' => 'sometimes|boolean',
            'order' => 'sometimes|integer|min:0',
            'active' => 'sometimes|boolean',
        ]);

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
