<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Infrastructure\Services\PlatformCompanyHealthService;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformCompanyHealthController extends Controller
{
    public function index(Request $request, PlatformCompanyHealthService $healthService): JsonResponse
    {
        DB::statement('SET search_path TO public');

        $limit = $request->integer('limit', 50);

        // #7302 — le portefeuille est mis en cache quelques dizaines de
        // secondes (donnée dérivée). `?refresh=1` force un recalcul : c'est ce
        // que déclenche le bouton « Actualiser » du back-office.
        if ($request->boolean('refresh')) {
            $healthService->forgetPortfolioCache($limit);
        }

        return new JsonResponse($healthService->portfolio(limit: $limit));
    }

    public function __invoke(string $companyId, PlatformCompanyHealthService $healthService): JsonResponse
    {
        $company = PlatformCompanyLookup::findOrFail($companyId);

        return new JsonResponse($healthService->build($company));
    }
}
