<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers;

use App\Modules\SearchIntelligence\Infrastructure\Services\SearchIntelOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GSI-9 (#8520) — vue d'ensemble « Growth & Search Intelligence ».
 *
 * `GET /api/v1/platform/search-intel/overview` — garde
 * `platform.permission:search_intel.view`, portée par les rôles marketing
 * (lecture+écriture), finance et ops (lecture).
 *
 * Tous les compteurs sont réels (cf. `SearchIntelOverviewService`) ; les états
 * absents sont explicitement signalés (`connected: false`, `metrics: null`).
 */
final class SearchIntelOverviewController
{
    public function __construct(
        private readonly SearchIntelOverviewService $overview,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lead_window_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        $days = (int) ($validated['lead_window_days'] ?? 30);

        return new JsonResponse([
            'data' => $this->overview->overview($days),
        ]);
    }
}
