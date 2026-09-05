<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * #6712 — Référentiel FuelStation consommé par le dashboard admin
 * (`FuelManagerView.vue`) : stations, incidents (équipements non actifs) et
 * rapprochements de caisse.
 *
 * Read-only, tenant-scoped (auth:sanctum + api.manager), pagination bornée.
 * NB : l'API « référentiel » complète est planifiée (#6391/#6373) — ici on
 * sert les VRAIES tables existantes (fuel_stations, fuel_cash_sessions,
 * fuel_pumps/fuel_tanks) pour débloquer l'UI sans contrat fantôme.
 */
final class FuelStationReferentialController extends Controller
{
    public function stations(Request $request): JsonResponse
    {
        $this->assertManager($request);

        $query = DB::table('fuel_stations')
            ->where('company_id', currentCompany()->id);

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $stations = $query
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 100), 1), 200))
            ->through(function ($station): array {
                return [
                    'id' => $station->id,
                    'code' => $station->code,
                    'name' => $station->name,
                    'address' => $station->address,
                    'phone' => $station->phone,
                    'timezone' => $station->timezone,
                    'currency' => $station->currency,
                    'status' => $station->status,
                    'created_at' => $station->created_at,
                ];
            });

        return new JsonResponse(['data' => $stations->items()]);
    }

    public function incidents(Request $request): JsonResponse
    {
        $this->assertManager($request);

        $companyId = currentCompany()->id;

        // Incidents dérivés : équipements (pompes/cuves) non actifs.
        // Pas de table d'incidents dédiée à ce stade (#6712) — l'UI affiche
        // le titre/type/priorité dérivés de l'état de l'équipement.
        $pumps = DB::table('fuel_pumps')
            ->where('company_id', $companyId)
            ->whereIn('status', ['inactive', 'retired'])
            ->get(['id', 'station_id', 'code', 'status', 'updated_at']);

        $tanks = DB::table('fuel_tanks')
            ->where('company_id', $companyId)
            ->whereIn('status', ['inactive', 'retired'])
            ->get(['id', 'station_id', 'code', 'status', 'updated_at']);

        $stations = DB::table('fuel_stations')
            ->where('company_id', $companyId)
            ->pluck('name', 'id');

        $incidents = [];

        foreach ($pumps as $pump) {
            $incidents[] = $this->incidentRow('pump', $pump, $stations, 'Pompe');
        }
        foreach ($tanks as $tank) {
            $incidents[] = $this->incidentRow('tank', $tank, $stations, 'Cuve');
        }

        // Tri par date décroissante (plus récent d'abord).
        usort($incidents, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return new JsonResponse(['data' => $incidents]);
    }

    public function reconciliations(Request $request): JsonResponse
    {
        $this->assertManager($request);

        $query = DB::table('fuel_cash_sessions')
            ->where('company_id', currentCompany()->id);

        // L'UI demande status=pending_review (rapprochements à valider) :
        // sessions closes non approuvées (+ approuvées pour l'historique).
        $status = (string) $request->query('status', '');
        if ($status === 'pending_review') {
            $query->whereIn('status', ['closed', 'approved']);
        } elseif ($status !== '') {
            $query->where('status', $status);
        }

        $reconciliations = $query
            ->orderByDesc('closed_at')
            ->paginate(min(max($request->integer('per_page', 100), 1), 200))
            ->through(function ($session): array {
                return [
                    'id' => $session->id,
                    'station_id' => $session->station_id,
                    'status' => $session->status,
                    'opened_at' => $session->opened_at,
                    'closed_at' => $session->closed_at,
                    'opening_balance' => $session->opening_balance,
                    'closing_balance' => $session->closing_balance,
                    'expected_balance' => $session->expected_balance,
                    'variance' => $session->variance,
                    'report_date' => $session->closed_at ?? $session->opened_at,
                    'approved_by' => $session->approved_by,
                ];
            });

        return new JsonResponse(['data' => $reconciliations->items()]);
    }

    private function assertManager(Request $request): void
    {
        /** @var Employee|null $actor */
        $actor = $request->user();
        abort_unless($actor?->isManager(), 403, 'FORBIDDEN');
    }

    /**
     * @param  Collection<int, string>  $stations
     * @return array<string, mixed>
     */
    private function incidentRow(string $equipmentType, object $equipment, $stations, string $label): array
    {
        $statusLabel = $equipment->status === 'retired' ? 'retiré' : 'inactif';

        return [
            'id' => $equipmentType.'-'.$equipment->id,
            'title' => "{$label} {$equipment->code} — {$statusLabel}",
            'description' => "Équipement {$equipment->code} (station ".($stations[$equipment->station_id] ?? '#'.$equipment->station_id).') '.$statusLabel.'.',
            'equipment_type' => $equipmentType,
            'equipment_code' => $equipment->code,
            'station_id' => $equipment->station_id,
            'priority' => $equipment->status === 'retired' ? 'high' : 'medium',
            'status' => $equipment->status,
            'created_at' => $equipment->updated_at,
        ];
    }
}
