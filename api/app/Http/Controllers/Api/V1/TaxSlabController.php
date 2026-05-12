<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Http\Controllers\Controller;
use App\Models\TaxSlab;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxSlabController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $query = TaxSlab::query();

        if ($request->filled('country_code')) {
            $query->forCountry($request->input('country_code'));
        }

        return response()->json([
            'data' => $query->orderBy('country_code')->orderBy('min_amount')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'country_code' => 'required|string|size:2|in:DZ,MA,TN,FR,TR,SN',
            'name' => 'required|string|max:150',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'rate' => 'required|numeric|min:0|max:100',
            'fixed_deduction' => 'nullable|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        $slab = TaxSlab::create([
            'company_id' => $actor->company_id,
            'country_code' => $validated['country_code'],
            'name' => $validated['name'],
            'min_amount' => $validated['min_amount'],
            'max_amount' => $validated['max_amount'] ?? null,
            'rate' => $validated['rate'],
            'fixed_deduction' => $validated['fixed_deduction'] ?? 0,
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
        ]);

        return response()->json(['data' => $slab], 201);
    }

    public function update(Request $request, TaxSlab $taxSlab): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'min_amount' => 'sometimes|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'rate' => 'sometimes|numeric|min:0|max:100',
            'fixed_deduction' => 'sometimes|numeric|min:0',
            'effective_from' => 'sometimes|date',
            'effective_to' => 'nullable|date',
        ]);

        $taxSlab->update($validated);

        return response()->json(['data' => $taxSlab->refresh()]);
    }

    public function destroy(Request $request, TaxSlab $taxSlab): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $taxSlab->delete();

        return response()->json(['message' => 'Tax slab deleted successfully.']);
    }
}
