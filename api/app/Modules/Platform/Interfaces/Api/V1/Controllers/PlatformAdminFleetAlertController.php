<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Alertes flotte — vue cross-tenant super-admin (contrat SPA admin,
 * issue #1764 : GET /v1/fleet/alerts appelé par FleetView sans exister
 * côté API).
 */
class PlatformAdminFleetAlertController extends Controller
{
    private const TENANT_SCHEMA = 'shared_tenants';

    /** @var array<string, string> */
    private const SEVERITY_BY_TYPE = [
        'sos' => 'critical',
        'speeding' => 'high',
        'geofence_exit' => 'high',
        'maintenance_due' => 'medium',
        'insurance_expiry' => 'medium',
        'low_fuel' => 'low',
        'idle' => 'low',
        'geofence_enter' => 'low',
    ];

    public function index(): JsonResponse
    {
        try {
            $alerts = DB::table(self::TENANT_SCHEMA.'.vehicle_alerts as va')
                ->leftJoin('companies', 'companies.id', '=', 'va.company_id')
                ->select([
                    'va.id',
                    'va.vehicle_id',
                    'va.company_id',
                    'companies.name as company_name',
                    'va.type',
                    'va.message',
                    'va.acknowledged',
                    'va.created_at',
                ])
                ->orderByDesc('va.created_at')
                ->limit(100)
                ->get()
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'vehicle_id' => (int) $row->vehicle_id,
                    'company_id' => $row->company_id,
                    'company_name' => $row->company_name,
                    'type' => $row->type,
                    'message' => $row->message,
                    'severity' => self::SEVERITY_BY_TYPE[$row->type] ?? 'medium',
                    'acknowledged' => (bool) $row->acknowledged,
                    'created_at' => $row->created_at,
                ])
                ->values();

            return response()->json(['data' => $alerts]);
        } catch (\Throwable) {
            return response()->json(['data' => []]);
        }
    }
}
