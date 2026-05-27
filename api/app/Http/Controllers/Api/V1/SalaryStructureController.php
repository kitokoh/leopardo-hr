<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SalaryStructureResource;
use App\Models\Employee;
use App\Models\SalaryStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\Payroll\StoreSalaryStructureRequest;
use App\Http\Requests\Api\V1\Payroll\UpdateSalaryStructureRequest;

class SalaryStructureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $query = SalaryStructure::where('company_id', $actor->company_id)
            ->withCount('components');

        if ($request->filled('country_code')) {
            $query->where('country_code', $request->input('country_code'));
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        return SalaryStructureResource::collection($query->orderBy('name')->get())->response();
    }

    public function store(StoreSalaryStructureRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

        $structure = SalaryStructure::create([
            'company_id' => $actor->company_id,
            'name' => $validated['name'],
            'base_salary' => $validated['base_salary'],
            'currency' => $validated['currency'],
            'country_code' => $validated['country_code'],
            'frequency' => $validated['frequency'] ?? 'monthly',
            'active' => true,
        ]);

        return (new SalaryStructureResource($structure))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, SalaryStructure $salaryStructure): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryStructure->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $salaryStructure->load('components');

        return (new SalaryStructureResource($salaryStructure))->response();
    }

    public function update(UpdateSalaryStructureRequest $request, SalaryStructure $salaryStructure): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryStructure->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

        $salaryStructure->update($validated);

        return (new SalaryStructureResource($salaryStructure->refresh()))->response();
    }

    public function destroy(Request $request, SalaryStructure $salaryStructure): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryStructure->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $salaryStructure->delete();

        return response()->json(['message' => 'Salary structure deleted successfully.']);
    }
}
