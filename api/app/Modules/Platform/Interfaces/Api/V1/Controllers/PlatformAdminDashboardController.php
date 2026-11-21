<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Infrastructure\Services\PlatformAdminStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cockpit super-admin — contrat SPA front/admin-dashboard (issue #1764).
 *
 * Les endpoints /admin/dashboard/* étaient appelés par le SPA admin sans
 * exister côté API (404 en production). Implémentation côté API (décision
 * produit) : agrégats réels depuis public.* et shared_tenants.*.
 *
 * Ce contrôleur ne porte que le contrat HTTP ; les agrégats et la
 * construction des alertes vivent dans `PlatformAdminStatsService`.
 */
class PlatformAdminDashboardController extends Controller
{
    public function __construct(
        private readonly PlatformAdminStatsService $stats,
    ) {}

    public function stats(): JsonResponse
    {
        return response()->json($this->stats->stats());
    }

    public function activities(): JsonResponse
    {
        return response()->json(['data' => $this->stats->activities()]);
    }

    public function alerts(): JsonResponse
    {
        return response()->json(['data' => $this->stats->alerts()]);
    }

    public function dismissAlert(Request $request, string $alertKey): JsonResponse
    {
        /** @var SuperAdmin|null $actor */
        $actor = $request->user();
        DB::table('public.platform_alert_dismissals')->updateOrInsert(
            ['alert_key' => $alertKey],
            ['dismissed_by' => $actor?->id, 'created_at' => now()]
        );

        return response()->json(['status' => 'dismissed'], 202);
    }
}
