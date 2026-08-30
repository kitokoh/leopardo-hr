<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelAlert;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Métriques d'observabilité FuelStation (FUEL-020, #5814).
 *
 * Snapshot tenant-scoped, SANS PII ni payloads : profondeur de la file
 * outbox, alertes ouvertes, fraîcheur des read models, volume de relevés
 * du jour, rapprochements en exception. Alimente les alertes queue/DB
 * (p95/p99 documentés dans docs/architecture/maturity/FUEL_020_OBSERVABILITY.md).
 */
class FuelMetricsController extends Controller
{
    public function metrics(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('fuel.metrics');

        $companyId = $actor->company_id;

        $outboxPending = (int) FuelOutboxEvent::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [FuelOutboxEvent::STATUS_PENDING, FuelOutboxEvent::STATUS_PROCESSING])
            ->count();

        $outboxFailed = (int) FuelOutboxEvent::query()
            ->where('company_id', $companyId)
            ->where('status', FuelOutboxEvent::STATUS_FAILED)
            ->count();

        $alertsOpen = (int) FuelAlert::query()
            ->where('company_id', $companyId)
            ->where('status', FuelAlert::STATUS_OPEN)
            ->count();

        $readingsToday = (int) FuelMeterReading::query()
            ->where('company_id', $companyId)
            ->where('captured_at_utc', '>=', now()->startOfDay())
            ->count();

        $snapshotsStale = (int) FuelReportSnapshot::query()
            ->where('company_id', $companyId)
            ->where('computed_at', '<', now()->subHours(24))
            ->count();

        $reconciliationsException = (int) FuelStockReconciliation::query()
            ->where('company_id', $companyId)
            ->where('status', FuelStockReconciliation::STATUS_EXCEPTION)
            ->count();

        return response()->json([
            'data' => [
                'outbox_pending' => $outboxPending,
                'outbox_failed' => $outboxFailed,
                'alerts_open' => $alertsOpen,
                'readings_today' => $readingsToday,
                'snapshots_stale' => $snapshotsStale,
                'reconciliations_exception' => $reconciliationsException,
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
