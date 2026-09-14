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

        // #7339 — pagination : `page` / `per_page` (bornés par le service, qui
        // plafonne à PORTFOLIO_MAX_PER_PAGE). `limit` reste accepté comme alias
        // de `per_page` : c'est le paramètre du contrat #7302, encore envoyé par
        // d'anciens appels. Un appel sans paramètre garde exactement la page
        // d'avant (page 1, 50 sociétés).
        $page = max(1, $request->integer('page', 1));
        $perPage = $request->has('per_page')
            ? $request->integer('per_page')
            : $request->integer('limit', PlatformCompanyHealthService::PORTFOLIO_DEFAULT_PER_PAGE);

        // #7302 — le portefeuille est mis en cache quelques dizaines de
        // secondes (donnée dérivée). `?refresh=1` force un recalcul : c'est ce
        // que déclenche le bouton « Actualiser » du back-office.
        if ($request->boolean('refresh')) {
            $healthService->forgetPortfolioCache($page, $perPage);
        }

        return new JsonResponse($healthService->portfolio(page: $page, perPage: $perPage));
    }

    public function __invoke(string $companyId, PlatformCompanyHealthService $healthService): JsonResponse
    {
        $company = PlatformCompanyLookup::findOrFail($companyId);

        return new JsonResponse($healthService->build($company));
    }
}
