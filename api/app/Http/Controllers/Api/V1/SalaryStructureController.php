<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SalaryStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryStructureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
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

        return response()->json([
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'base_salary' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'country_code' => 'required|string|size:2|in:DZ,MA,TN,FR,TR,SN',
            'frequency' => 'nullable|in:monthly,bi_weekly,weekly',
        ]);

        $structure = SalaryStructure::create([
            'company_id' => $actor->company_id,
            'name' => $validated['name'],
            'base_salary' => $validated['base_salary'],
            'currency' => $validated['currency'],
            'country_code' => $validated['country_code'],
            'frequency' => $validated['frequency'] ?? 'monthly',
            'active' => true,
        ]);

        return response()->json(['data' => $structure], 201);
    }

    public function show(Request $request, SalaryStructure $salaryStructure): JsonResponse
    {
        $actor = $request->user();
        if ($salaryStructure->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $salaryStructure->load('components');

        return response()->json(['data' => $salaryStructure]);
    }

    public function update(Request $request, SalaryStructure $salaryStructure): JsonResponse
    {
        $actor = $request->user();
        if ($salaryStructure->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'base_salary' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'country_code' => 'sometimes|string|size:2|in:DZ,MA,TN,FR,TR,SN',
            'frequency' => 'sometimes|in:monthly,bi_weekly,weekly',
            'active' => 'sometimes|boolean',
        ]);

        $salaryStructure->update($validated);

        return response()->json(['data' => $salaryStructure->refresh()]);
    }

    public function destroy(Request $request, SalaryStructure $salaryStructure): JsonResponse
    {
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
