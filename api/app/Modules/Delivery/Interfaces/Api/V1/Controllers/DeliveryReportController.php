<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Controllers;

use App\Modules\Delivery\Application\Services\DeliveryReportService;
use App\Modules\Delivery\Domain\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapports & KPIs livraison (DELIVERY-207, issue #6291) — RBAC manager
 * (`api.manager`, la matrice `delivery.reports` est BC-26-D05).
 *
 * Read model déterministe scopé `company_id` (fenêtre de dates explicite,
 * bornes par défaut 30 jours) + export CSV synchrone streamé (léger, pas de
 * job — l'export async est le scope de BC-26-D07 asynchronisme).
 */
final class DeliveryReportController
{
    private const DEFAULT_DAYS = 30;

    public function __construct(private readonly DeliveryReportService $reports) {}

    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $companyId = $this->companyId($request);

        return response()->json([
            'data' => $this->reports->summary($companyId, $from, $to),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $companyId = $this->companyId($request);

        $deliveries = Delivery::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            // BC-26-D10 (#6296) : export streamé via curseur — pas de
            // get() non paginé (garde MAT-014), mémoire bornée.
            ->cursor();

        $stream = function () use ($deliveries): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'reference', 'source', 'type', 'status', 'cod_amount_minor',
                'dropoff_address', 'created_at', 'delivered_at',
            ]);

            foreach ($deliveries as $delivery) {
                fputcsv($handle, [
                    $delivery->reference,
                    $delivery->source,
                    $delivery->type,
                    $delivery->status,
                    $delivery->cod_amount_minor,
                    $delivery->dropoff_address,
                    $delivery->created_at?->toIso8601String(),
                    $delivery->delivered_at?->toIso8601String(),
                ]);
            }

            fclose($handle);
        };

        $filename = sprintf('deliveries-%s.csv', $to->toDateString());

        return response()->streamDownload($stream, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function dateRange(Request $request): array
    {
        $to = $request->filled('to')
            ? Carbon::parse((string) $request->string('to'))->endOfDay()
            : now();

        $from = $request->filled('from')
            ? Carbon::parse((string) $request->string('from'))->startOfDay()
            : (clone $to)->subDays(self::DEFAULT_DAYS);

        if ($from->greaterThan($to)) {
            abort(422, 'INVALID_DATE_RANGE');
        }

        return [$from, $to];
    }

    private function companyId(Request $request): string
    {
        $companyId = $request->user()?->getAttribute('company_id');

        if (! is_string($companyId) || $companyId === '') {
            abort(403, 'Tenant context missing.');
        }

        return $companyId;
    }
}
