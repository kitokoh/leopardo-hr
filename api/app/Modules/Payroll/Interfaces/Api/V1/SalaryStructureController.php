<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SalaryStructureResource;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Rules\SupportedCountry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'base_salary' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'country_code' => ['required', 'string', 'size:2', new SupportedCountry], // #1951 contrat partagé
            'frequency' => 'nullable|in:monthly,bi_weekly,weekly',
        ]);

        // MULTI-PAYS (#1867) : une structure salariale est liée au pays légal
        // du tenant — un client ne peut pas la créer pour un autre pays.
        $this->assertCountryMatchesTenant($request, (string) $validated['country_code']);

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

    public function update(Request $request, SalaryStructure $salaryStructure): JsonResponse
    {
        /** @var Employee $actor */
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
            'country_code' => ['sometimes', 'string', 'size:2', new SupportedCountry], // #1951 contrat partagé
            'frequency' => 'sometimes|in:monthly,bi_weekly,weekly',
            'active' => 'sometimes|boolean',
        ]);

        // MULTI-PAYS (#1867) : le pays d'une structure ne peut pas être
        // modifié vers un pays différent du pays légal du tenant.
        if (isset($validated['country_code'])) {
            $this->assertCountryMatchesTenant($request, (string) $validated['country_code']);
        }

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

        return response()->json(['message' => __('payroll.salary_structure_deleted')]);
    }

    /**
     * MULTI-PAYS (#1867) — verrou : une structure salariale (ou un run) doit
     * porter le pays légal du tenant. Un `country_code` client différent est
     * refusé explicitement (422), jamais contourné.
     */
    private function assertCountryMatchesTenant(Request $request, string $countryCode): void
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $company = $actor->company;
        $tenantCountry = $company instanceof Company
            ? strtoupper((string) $company->country)
            : null;

        if ($tenantCountry !== null && strtoupper($countryCode) !== $tenantCountry) {
            abort(422, __('errors.PAYROLL_COUNTRY_MISMATCH', ['country' => $tenantCountry]));
        }
    }
}
