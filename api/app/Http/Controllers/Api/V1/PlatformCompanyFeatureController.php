<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\FeatureFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\Platform\UpdatePlatformCompanyFeatureRequest;

class PlatformCompanyFeatureController extends Controller
{
    public function show(string $companyId): JsonResponse
    {
        $company = Company::query()->findOrFail($companyId);

        return new JsonResponse([
            'data' => [
                'company_id' => $company->id,
                'features' => FeatureFlag::for($company),
                'known_modules' => Company::KNOWN_MODULES,
            ],
        ]);
    }

    public function update(UpdatePlatformCompanyFeatureRequest $request, string $companyId): JsonResponse
    {
        $company = Company::query()->findOrFail($companyId);

        $validated = $request->validated();

        $features = [];
        foreach (Company::KNOWN_MODULES as $module) {
            $features[$module] = $module === 'rh'
                ? true
                : (bool) ($validated['features'][$module] ?? false);
        }

        $company->features = $features;
        $company->save();

        return new JsonResponse([
            'data' => [
                'company_id' => $company->id,
                'features' => FeatureFlag::for($company->fresh()),
            ],
        ]);
    }
}
