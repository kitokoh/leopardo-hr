<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Services\TravelReportService;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\TravelReportRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-501..504 / 507 (#6071..#6074, #6077) — Rapports & dashboard travel.
 *
 * Endpoints agrégés (période, trajet, route, source) : ventes, occupation,
 * recettes, annulations, KPIs du jour. Permission `travel.reports` (Gate).
 * Chiffres TOUJOURS recalculés serveur en minor units.
 */
class TravelReportController extends Controller
{
    public function __construct(private readonly TravelReportService $reports)
    {
    }

    public function sales(TravelReportRequest $request): JsonResponse
    {
        return $this->report($request, fn (string $company, CarbonImmutable $from, CarbonImmutable $to, array $f): array => $this->reports->sales(
            $company, $from, $to, $f['trip_id'], $f['route_id'], $f['source'], $f['status'],
        ));
    }

    public function occupancy(TravelReportRequest $request): JsonResponse
    {
        return $this->report($request, fn (string $company, CarbonImmutable $from, CarbonImmutable $to, array $f): array => $this->reports->occupancy(
            $company, $from, $to, $f['trip_id'], $f['route_id'],
        ));
    }

    public function revenue(TravelReportRequest $request): JsonResponse
    {
        return $this->report($request, fn (string $company, CarbonImmutable $from, CarbonImmutable $to, array $f): array => $this->reports->revenue(
            $company, $from, $to, $f['trip_id'], $f['source'],
        ));
    }

    public function cancellations(TravelReportRequest $request): JsonResponse
    {
        return $this->report($request, fn (string $company, CarbonImmutable $from, CarbonImmutable $to, array $f): array => $this->reports->cancellations(
            $company, $from, $to, $f['trip_id'], $f['source'],
        ));
    }

    public function dashboard(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('travel.reports')) {
            abort(403);
        }

        $tripId = $request->query('trip_id');
        $tripId = is_numeric($tripId) ? (int) $tripId : null;

        $days = $request->query('days');
        $days = is_numeric($days) ? max(1, min(90, (int) $days)) : 1;

        return new JsonResponse(['data' => $this->reports->dashboard((string) $actor->company_id, $tripId, $days)]);
    }

    /**
     * @param  callable(string, CarbonImmutable, CarbonImmutable, array<string, mixed>): array  $aggregate
     */
    private function report(TravelReportRequest $request, callable $aggregate): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('travel.reports')) {
            abort(403);
        }

        $v = $request->validated();

        $tripId = is_numeric($v['trip_id'] ?? null) ? (int) $v['trip_id'] : null;
        $routeId = is_numeric($v['route_id'] ?? null) ? (int) $v['route_id'] : null;

        $payload = $aggregate(
            (string) $actor->company_id,
            CarbonImmutable::parse((string) $v['from']),
            CarbonImmutable::parse((string) $v['to']),
            [
                'trip_id' => $tripId,
                'route_id' => $routeId,
                'source' => $v['source'] ?? null,
                'status' => $v['status'] ?? null,
            ],
        );

        return new JsonResponse(['data' => $payload]);
    }
}
