<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\PlatformCompanyHealthService;
use Illuminate\Http\JsonResponse;

class PlatformCompanyHealthController extends Controller
{
    public function __invoke(string $companyId, PlatformCompanyHealthService $healthService): JsonResponse
    {
        $company = Company::query()->findOrFail($companyId);

        return new JsonResponse($healthService->build($company));
    }
}
