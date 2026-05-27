<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaxSlabResource;
use App\Models\Employee;
use App\Models\TaxSlab;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\Payroll\StoreTaxSlabRequest;
use App\Http\Requests\Api\V1\Payroll\UpdateTaxSlabRequest;

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

        return TaxSlabResource::collection($query->orderBy('country_code')->orderBy('min_amount')->get())->response();
    }

    public function store(StoreTaxSlabRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

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

        return (new TaxSlabResource($slab))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTaxSlabRequest $request, TaxSlab $taxSlab): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

        $taxSlab->update($validated);

        return (new TaxSlabResource($taxSlab->refresh()))->response();
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
